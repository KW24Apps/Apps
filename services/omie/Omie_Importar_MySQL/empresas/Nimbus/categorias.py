import pandas as pd
from omie_api import OmieAPI
from database_utils import salvar_com_fallback

def etl_categorias_nimbus():
    api = OmieAPI()
    print('Iniciando ETL de categorias Nimbus...')
    parametros = {
        'pagina': 1,
        'registros_por_pagina': 100
    }
    registros = api.buscar_todas_paginas(
        endpoint='geral/categorias/',
        call='ListarCategorias',
        parametros=parametros,
        data_key='categoria_cadastro',
        registros_por_pagina=100
    )
    print(f'Total de registros recebidos: {len(registros)}')
    df = pd.json_normalize(registros, sep='.')
    # Expande dadosDRE se existir
    if 'dadosDRE' in df.columns:
        expanded = pd.json_normalize(df['dadosDRE'].apply(lambda x: x if isinstance(x, dict) else {}), sep='_')
        expanded.columns = [f'dadosDRE_{c}' for c in expanded.columns]
        expanded = expanded.reindex(df.index)
        df = pd.concat([df, expanded], axis=1)
        df = df.drop(columns=['dadosDRE'])
    # Adiciona colunas fixas da empresa
    import os
    empresa_nome = os.getenv('EMPRESA_NOME', 'Nimbus')
    empresa_cnpj = os.getenv('EMPRESA_CNPJ', '51.010.674/0001-08')
    df['Minha Empresa Nome'] = empresa_nome
    df['Minha Empresa CNPJ'] = empresa_cnpj

    tabela = f"categorias_{empresa_nome.lower()}"
    salvar_com_fallback(df, tabela)
    print('ETL de categorias finalizado.')

if __name__ == '__main__':
    etl_categorias_nimbus()
