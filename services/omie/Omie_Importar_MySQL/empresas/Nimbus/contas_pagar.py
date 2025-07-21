import pandas as pd
from omie_api import OmieAPI
from database_utils import salvar_com_fallback

def etl_contas_pagar_nimbus():
    api = OmieAPI()
    print('Iniciando ETL de contas a pagar Nimbus...')
    parametros = {
        'pagina': 1,
        'registros_por_pagina': 100,
        'apenas_importado_api': 'N'
    }
    registros = api.buscar_todas_paginas(
        endpoint='financas/contapagar/',
        call='ListarContasPagar',
        parametros=parametros,
        data_key='conta_pagar_cadastro',
        registros_por_pagina=100
    )
    print(f'Total de registros recebidos: {len(registros)}')
    df = pd.json_normalize(registros, sep='.')
    # Adiciona colunas fixas da empresa, lidas do .env ou padrão Nimbus
    import os
    empresa_nome = os.getenv('EMPRESA_NOME', 'Nimbus')
    empresa_cnpj = os.getenv('EMPRESA_CNPJ', '51.010.674/0001-08')
    df['Minha Empresa Nome'] = empresa_nome
    df['Minha Empresa CNPJ'] = empresa_cnpj

    # EXPANSÃO AUTOMÁTICA DE categorias_* e distribuicao_*
    import re
    import json
    # Expande apenas categorias_0 e distribuicao_0
    import json
    for col_base in ['categorias', 'distribuicao']:
        # Detecta colunas como 'categorias', 'categorias_0', ...
        cols = [c for c in df.columns if c.startswith(col_base)]
        for col in cols:
            try:
                def parse_list(val):
                    if isinstance(val, list):
                        return val
                    elif isinstance(val, str):
                        try:
                            parsed = json.loads(val)
                            if isinstance(parsed, list):
                                return parsed
                            elif isinstance(parsed, dict):
                                return [parsed]
                            else:
                                return []
                        except Exception:
                            return []
                    elif isinstance(val, dict):
                        return [val]
                    else:
                        return []
                # Cria DataFrame expandido para cada item da lista
                expanded_items = df[col].apply(parse_list)
                max_len = expanded_items.apply(len).max()
                for i in range(max_len):
                    expanded = pd.json_normalize(expanded_items.apply(lambda x: x[i] if i < len(x) else {}), sep='_')
                    expanded.columns = [f'{col}_{i}_{c}' for c in expanded.columns]
                    expanded = expanded.reindex(df.index)
                    df = pd.concat([df, expanded], axis=1)
                df = df.drop(columns=[col])
            except Exception as e:
                print(f'Falha ao expandir coluna {col}: {e}')

    # Salva no MySQL com fallback
    tabela = f"contas_pagar_{empresa_nome.lower()}"
    salvar_com_fallback(df, tabela)
    print('ETL de contas a pagar finalizado.')

if __name__ == '__main__':
    etl_contas_pagar_nimbus()
