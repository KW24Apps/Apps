"""
Script para importar Movimentos Financeiros da Omie para MySQL
Empresa: Nimbus
Tabela: movimentos_financeiros_nimbus
"""

import pandas as pd
from pandas import json_normalize
from config import print_config
from omie_api import OmieAPI
from database_utils import salvar_com_fallback

def explode_json(movimentos):
    """Processa e normaliza os dados JSON dos movimentos financeiros"""
    movimentos_com_deps = [m for m in movimentos if 'departamentos' in m and isinstance(m['departamentos'], list) and m['departamentos']]
    movimentos_sem_deps = [m for m in movimentos if not ('departamentos' in m and isinstance(m['departamentos'], list) and m['departamentos'])]

    # Função auxiliar para renomear colunas duplicadas
    def rename_duplicate_columns(df, existing_columns, suffix_start=1):
        new_columns = []
        seen = {}
        for col in df.columns:
            if col not in existing_columns:
                new_columns.append(col)
            else:
                count = seen.get(col, suffix_start)
                new_col = f"{col}_{count}"
                while new_col in existing_columns or new_col in new_columns:
                    count += 1
                    new_col = f"{col}_{count}"
                seen[col] = count + 1
                new_columns.append(new_col)
        df.columns = new_columns
        return df

    # Normalizar movimentos com departamentos
    if movimentos_com_deps:
        df_deps = json_normalize(
            movimentos_com_deps,
            record_path=['departamentos'],
            meta=[
                'nCodMovCC', 'nCodMovCCRepet', 'nCodTitulo', 'nValorMovCC',
                'detalhes', 'resumo', 'categorias'
            ],
            errors='ignore'
        )
    else:
        df_deps = pd.DataFrame()

    # Normalizar movimentos sem departamentos
    if movimentos_sem_deps:
        df_sem = json_normalize(
            movimentos_sem_deps,
            meta=[
                'nCodMovCC', 'nCodMovCCRepet', 'nCodTitulo', 'nValorMovCC',
                'detalhes', 'resumo', 'categorias'
            ],
            errors='ignore'
        )
    else:
        df_sem = pd.DataFrame()

    # Concatenar os DataFrames iniciais
    df = pd.concat([df_deps, df_sem], ignore_index=True)

    # Processar categorias
    if 'categorias' in df.columns:
        df = df.explode('categorias')
        categorias_df = pd.json_normalize(df['categorias'].dropna())
        # Renomear colunas duplicadas
        categorias_df = rename_duplicate_columns(categorias_df, df.columns)
        df = pd.concat([df.drop(columns=['categorias']).reset_index(drop=True), categorias_df.reset_index(drop=True)], axis=1)

    # Processar detalhes
    if 'detalhes' in df.columns:
        detalhes_df = pd.json_normalize(df['detalhes'])
        # Renomear colunas duplicadas
        detalhes_df = rename_duplicate_columns(detalhes_df, df.columns)
        df = pd.concat([df.drop(columns=['detalhes']).reset_index(drop=True), detalhes_df.reset_index(drop=True)], axis=1)

    # Processar resumo
    if 'resumo' in df.columns:
        resumo_df = pd.json_normalize(df['resumo'])
        # Renomear colunas duplicadas
        resumo_df = rename_duplicate_columns(resumo_df, df.columns)
        df = pd.concat([df.drop(columns=['resumo']).reset_index(drop=True), resumo_df.reset_index(drop=True)], axis=1)

    return df

def processar_colunas_customizadas(df):
    """Adiciona colunas customizadas específicas para movimentos financeiros"""
    # Criar a coluna "Valor Rec/Pag"
    df['Valor Rec/Pag'] = df['nDistrValor_1'].where(df['nDistrValor_1'].notna() & (df['nDistrValor_1'] != 0), df['nDistrValor'])
    df['Valor Rec/Pag'] = df['Valor Rec/Pag'].where(df['Valor Rec/Pag'].notna() & (df['Valor Rec/Pag'] != 0), df['nValPago'])

    # Garantir que valores sejam tratados corretamente como float
    df['Valor Rec/Pag'] = pd.to_numeric(df['Valor Rec/Pag'], errors='coerce')

    # Marcar duplicados considerando cCodDepartamento também
    df['Valor duplicado'] = df.duplicated(
        subset=['nCodMovCCRepet_1', 'Valor Rec/Pag', 'cCodDepartamento'],
        keep='first'
    )
    df['Valor duplicado'] = df['Valor duplicado'].map({True: 'duplicado', False: 'apenas'})

    # Criar coluna final: zera valores duplicados
    df['Valor Rec/Pag Final'] = df.apply(
        lambda row: row['Valor Rec/Pag'] if row['Valor duplicado'] == 'apenas' else None,
        axis=1
    )
    
    return df

def main():
    """Função principal"""
    print_config()
    
    # Inicializar API
    api = OmieAPI()
    
    # Parâmetros para buscar movimentos financeiros
    parametros = {
        "cTpLancamento": "CC",
        "cExibirDepartamentos": "S"
    }
    
    # Buscar dados da API
    print("Iniciando busca de movimentos financeiros...")
    movimentos = api.buscar_todas_paginas(
        endpoint="financas/mf/",
        call="ListarMovimentos",
        parametros=parametros,
        data_key="movimentos"
    )
    
    print(f"Total de movimentos encontrados: {len(movimentos)}")
    
    # Processar dados
    df = explode_json(movimentos)
    df = processar_colunas_customizadas(df)
    
    # Adiciona colunas fixas da empresa, lidas do .env ou padrão Nimbus
    import os
    empresa_nome = os.getenv('EMPRESA_NOME', 'Nimbus')
    empresa_cnpj = os.getenv('EMPRESA_CNPJ', '51.010.674/0001-08')
    df['Minha Empresa Nome'] = empresa_nome
    df['Minha Empresa CNPJ'] = empresa_cnpj
    # Salvar no banco
    tabela = f"movimentos_financeiros_{empresa_nome.lower()}"
    salvar_com_fallback(df, tabela)
    
    # Exibir amostra dos dados
    print("\nAmostra dos dados processados:")
    print(df.head(3))

if __name__ == "__main__":
    main()