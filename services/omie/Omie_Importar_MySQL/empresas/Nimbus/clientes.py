import pandas as pd
from omie_api import OmieAPI
from database_utils import salvar_com_fallback

def etl_clientes_nimbus():
    api = OmieAPI()
    print('Iniciando ETL de clientes Nimbus...')
    parametros = {
        'pagina': 1,
        'registros_por_pagina': 100,
        'apenas_importado_api': 'N'
    }
    registros = api.buscar_todas_paginas(
        endpoint='geral/clientes/',
        call='ListarClientes',
        parametros=parametros,
        data_key='clientes_cadastro',
        registros_por_pagina=100
    )
    print(f'Total de registros recebidos: {len(registros)}')
    df = pd.json_normalize(registros, sep='.')
    # Adiciona colunas fixas da empresa
    import os
    empresa_nome = os.getenv('EMPRESA_NOME', 'Nimbus')
    empresa_cnpj = os.getenv('EMPRESA_CNPJ', '51.010.674/0001-08')
    df['Minha Empresa Nome'] = empresa_nome
    df['Minha Empresa CNPJ'] = empresa_cnpj

    # EXPANSÃO AUTOMÁTICA DE info, dadosBancarios, recomendacoes, tags
    import json
    def expand_dict_column(df, col):
        if col in df.columns:
            expanded = pd.json_normalize(df[col].apply(lambda x: x if isinstance(x, dict) else {}), sep='_')
            expanded.columns = [f'{col}_{c}' for c in expanded.columns]
            expanded = expanded.reindex(df.index)
            df = pd.concat([df, expanded], axis=1)
            df = df.drop(columns=[col])
        return df
    def expand_list_of_dicts_column(df, col):
        if col in df.columns:
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
            expanded_items = df[col].apply(parse_list)
            max_len = expanded_items.apply(len).max()
            for i in range(max_len):
                expanded = pd.json_normalize(expanded_items.apply(lambda x: x[i] if i < len(x) else {}), sep='_')
                expanded.columns = [f'{col}_{i}_{c}' for c in expanded.columns]
                expanded = expanded.reindex(df.index)
                df = pd.concat([df, expanded], axis=1)
            df = df.drop(columns=[col])
        return df
    for col in ['info', 'dadosBancarios', 'recomendacoes']:
        df = expand_dict_column(df, col)
    df = expand_list_of_dicts_column(df, 'tags')

    tabela = f"clientes_{empresa_nome.lower()}"
    salvar_com_fallback(df, tabela)
    print('ETL de clientes finalizado.')

if __name__ == '__main__':
    etl_clientes_nimbus()
