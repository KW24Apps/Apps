document.addEventListener('DOMContentLoaded', () => {
    const progressBar = document.getElementById('progressBar');
    const statusText = document.getElementById('statusText');
    let intervalId = null;

    if (!progressBar || !statusText || typeof jobIds === 'undefined' || jobIds.length === 0) {
        console.error('Elementos essenciais para o status não foram encontrados.');
        return;
    }

    async function verificarStatus() {
        try {
            const jobsQueryParam = jobIds.join(',');
            const url = `/Apps/public/form/api/status_job.php?cliente=${encodeURIComponent(cliente)}&jobs=${encodeURIComponent(jobsQueryParam)}`;
            
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`Erro na requisição: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.sucesso) {
                const processados = data.total_processado || 0;
                const progresso = totalRegistros > 0 ? (processados / totalRegistros) * 100 : 0;

                progressBar.style.width = `${progresso}%`;
                statusText.textContent = `Processando... ${processados} de ${totalRegistros} registros concluídos.`;

                if (processados >= totalRegistros) {
                    clearInterval(intervalId);
                    statusText.textContent = `✅ Concluído! ${processados} registros foram importados com sucesso.`;
                    progressBar.style.backgroundColor = '#28a745'; // Verde sucesso
                }
            } else {
                throw new Error(data.mensagem || 'A API retornou um erro.');
            }

        } catch (error) {
            console.error('Erro ao verificar status:', error);
            statusText.textContent = '⚠️ Erro ao verificar o status. Tente recarregar a página.';
            clearInterval(intervalId);
        }
    }

    // Inicia a verificação
    verificarStatus(); // Chama imediatamente na primeira vez
    intervalId = setInterval(verificarStatus, 5000); // E depois a cada 5 segundos
});
