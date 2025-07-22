import socket
import pandas as pd
import pymysql
from config import MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE

def testar_conexao():
    """Testa a conectividade com o servidor MySQL"""
    try:
        print(f"Testando conectividade com {MYSQL_HOST}:{MYSQL_PORT}")
        sock = socket.create_connection((MYSQL_HOST, MYSQL_PORT), timeout=10)
        sock.close()
        print("OK - Conectividade TCP estabelecida com sucesso!")
        return True
    except socket.gaierror as e:
        print(f"ERRO - Erro de resolução DNS: {e}")
        return False
    except socket.timeout:
        print("ERRO - Timeout na conexão")
        return False
    except Exception as e:
        print(f"ERRO - Erro de conectividade: {e}")
        return False

def salvar_mysql(df, tabela):
    """Estratégia principal: SQLAlchemy + PyMySQL"""
    try:
        # Primeiro, testar conectividade
        if not testar_conexao():
            # Nome genérico de backup por ETL (remove sufixo de empresa)
            nome_backup = tabela.split('_')[0] + '_backup.csv'
            print("Falha na conectividade. Tentando salvar em arquivo CSV como backup...")
            df.to_csv(nome_backup, index=False, encoding='utf-8-sig')
            print(f"Dados salvos em {nome_backup}")
            return False
        
        print("Tentando conectar ao MySQL...")
        conn = pymysql.connect(
            host=MYSQL_HOST,
            user=MYSQL_USER,
            password=MYSQL_PASSWORD,
            database=MYSQL_DATABASE,
            port=MYSQL_PORT,
            charset='utf8mb4',
            connect_timeout=30,
            read_timeout=30,
            write_timeout=30
        )
        
        print("✓ Conexão PyMySQL estabelecida!")
        
        with conn:
            from sqlalchemy import create_engine
            # Usar parâmetros de timeout também no SQLAlchemy
            connection_string = f'mysql+pymysql://{MYSQL_USER}:{MYSQL_PASSWORD}@{MYSQL_HOST}:{MYSQL_PORT}/{MYSQL_DATABASE}?connect_timeout=30&read_timeout=30&write_timeout=30'
            engine = create_engine(connection_string)
            
            print("Colunas do DataFrame antes de salvar:", df.columns.tolist())
            print(f"Salvando {len(df)} registros na tabela {tabela}...")
            
            df.to_sql(tabela, engine, if_exists='replace', index=False, method='multi', chunksize=1000)
            print(f'✓ Tabela {tabela} criada com sucesso!')
            return True
            
    except pymysql.err.OperationalError as e:
        print(f"Erro de conexão MySQL: {str(e)}")
        nome_backup = tabela.split('_')[0] + '_backup.csv'
        print("Salvando dados em arquivo CSV como backup...")
        df.to_csv(nome_backup, index=False, encoding='utf-8-sig')
        print(f"Dados salvos em {nome_backup}")
        return False
    except Exception as e:
        print(f"Erro ao salvar no MySQL: {str(e)}")
        nome_backup = tabela.split('_')[0] + '_backup.csv'
        print("Salvando dados em arquivo CSV como backup...")
        df.to_csv(nome_backup, index=False, encoding='utf-8-sig')
        print(f"Dados salvos em {nome_backup}")
        return False

def salvar_mysql_alternativo(df, tabela):
    """Estratégia alternativa: PyMySQL direto"""
    try:
        print("Estratégia alternativa: Conexão direta com PyMySQL...")
        conn = pymysql.connect(
            host=MYSQL_HOST,
            user=MYSQL_USER,
            password=MYSQL_PASSWORD,
            database=MYSQL_DATABASE,
            port=MYSQL_PORT,
            charset='utf8mb4',
            connect_timeout=30,
            autocommit=True
        )
        
        with conn:
            import numpy as np
            import pandas as pd
            import json
            cursor = conn.cursor()
            # EXPANSÃO AUTOMÁTICA DE COLUNAS COM LISTAS DE DICTS
            cols_to_expand = []
            for col in df.columns:
                # Detecta se a coluna tem pelo menos um valor que é list de dicts
                if df[col].apply(lambda x: isinstance(x, list) and len(x) > 0 and isinstance(x[0], dict)).any():
                    cols_to_expand.append(col)
            # Para cada coluna a expandir
            for col in cols_to_expand:
                expanded = pd.json_normalize(df[col], sep='_')
                expanded.columns = [f'{col}_{c}' for c in expanded.columns]
                expanded = expanded.reindex(df.index)
                df = pd.concat([df.drop(columns=[col]), expanded], axis=1)

            # EXPANSÃO ESPECÍFICA PARA conta_receber_nimbus: categorias_* e distribuicao_*
            if tabela == 'conta_receber_nimbus':
                import re
                # Detecta todas as colunas que seguem o padrão categorias_* ou distribuicao_*
                padroes = ['categorias_\d+', 'distribuicao_\d+']
                cols_json = [col for col in df.columns if any(re.match(p, col) for p in padroes)]
                for col in cols_json:
                    # Expande o JSON para colunas separadas, aceitando dict ou string
                    try:
                        def parse_json(val):
                            if isinstance(val, dict):
                                return val
                            elif isinstance(val, str):
                                return json.loads(val)
                            else:
                                return None
                        expanded = pd.json_normalize(df[col].dropna().apply(parse_json), sep='_')
                        expanded.columns = [f'{col}_{c}' for c in expanded.columns]
                        expanded = expanded.reindex(df.index)
                        df = pd.concat([df.drop(columns=[col]), expanded], axis=1)
                    except Exception as e:
                        print(f'Falha ao expandir coluna {col}: {e}')
            # Criar tabela se não existir
            colunas_sql = []
            colunas_double = [
                'cCodDepartamento', 'nDistrPercentual', 'nDistrValor', 'nCodMovCC', 'nCodMovCCRepet', 'nCodTitulo', 'nValorMovCC',
                'detalhes.nCodCC', 'detalhes.nCodMovCC', 'detalhes.nCodMovCCRepet', 'detalhes.nCodTitulo', 'detalhes.nValorMovCC',
                'resumo.nValPago', 'detalhes.nCodBaixa', 'detalhes.nCodCliente', 'nDistrPercentual_1', 'nDistrValor_1',
                'nCodBaixa', 'nCodCC', 'nCodCliente', 'nCodMovCC_1', 'nCodMovCCRepet_1', 'nCodTitulo_1', 'nValorMovCC_1',
                'cCodProjeto', 'nJuros', 'nMulta', 'nDesconto', 'nValPago', 'Valor Rec/Pag', 'Valor Rec/Pag Final'
            ]
            for col in df.columns:
                col_clean = col.replace('.', '_').replace(' ', '_').replace('/', '_')
                if col in colunas_double:
                    colunas_sql.append(f"`{col_clean}` DOUBLE")
                else:
                    colunas_sql.append(f"`{col_clean}` TEXT")
            create_table_sql = f"""
            CREATE TABLE IF NOT EXISTS `{tabela}` (
                {', '.join(colunas_sql)}
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """
            cursor.execute(f"DROP TABLE IF EXISTS `{tabela}`")
            cursor.execute(create_table_sql)
            # Inserir dados em lotes
            colunas_clean = [col.replace('.', '_').replace(' ', '_').replace('/', '_') for col in df.columns]
            placeholders = ', '.join(['%s'] * len(colunas_clean))
            insert_sql = f"INSERT INTO `{tabela}` ({', '.join([f'`{col}`' for col in colunas_clean])}) VALUES ({placeholders})"
            # Converter DataFrame para lista de tuplas
            data_to_insert = []
            for _, row in df.iterrows():
                row_data = []
                for val in row:
                    # Se for array, Series, list ou dict, sempre converte para JSON
                    if isinstance(val, (np.ndarray, pd.Series, list, dict)):
                        if isinstance(val, (np.ndarray, pd.Series)):
                            row_data.append(json.dumps(val.tolist(), ensure_ascii=False))
                        else:
                            row_data.append(json.dumps(val, ensure_ascii=False))
                    elif pd.isna(val):
                        row_data.append(None)
                    else:
                        row_data.append(str(val))
                data_to_insert.append(tuple(row_data))
            batch_size = 100
            for i in range(0, len(data_to_insert), batch_size):
                batch = data_to_insert[i:i + batch_size]
                cursor.executemany(insert_sql, batch)
                print(f"Inseridos {min(i + batch_size, len(data_to_insert))} de {len(data_to_insert)} registros")
            print(f'✓ Tabela {tabela} criada com sucesso usando PyMySQL direto!')
            return True
            
    except Exception as e:
        print(f"Estratégia alternativa falhou: {str(e)}")
        return False

def salvar_com_fallback(df, tabela):
    """Função principal que tenta as duas estratégias"""
    print(f"Total de linhas processadas: {len(df)}")
    
    # Tentar primeira estratégia (SQLAlchemy)
    if salvar_mysql(df, tabela):
        return True
    
    # Tentar estratégia alternativa
    print("\nTentando estratégia alternativa...")
    if salvar_mysql_alternativo(df, tabela):
        return True
    
    print("Todas as estratégias falharam. Dados salvos em CSV.")
    return False
