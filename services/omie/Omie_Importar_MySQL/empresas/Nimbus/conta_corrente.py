import pandas as pd
from omie_api import OmieAPI
from database_utils import salvar_com_fallback

def etl_conta_corrente_nimbus():
    api = OmieAPI()
    print('Iniciando ETL de conta corrente Nimbus...')
    parametros = {
        'pagina': 1,
        'registros_por_pagina': 100,
        'apenas_importado_api': 'N'
    }
    registros = api.buscar_todas_paginas(
        endpoint='geral/contacorrente/',
        call='ListarContasCorrentes',
        parametros=parametros,
        data_key='ListarContasCorrentes',
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

    tabela = f"conta_corrente_{empresa_nome.lower()}"
    salvar_com_fallback(df, tabela)
    print('ETL de conta corrente finalizado.')

if __name__ == '__main__':
    etl_conta_corrente_nimbus()
