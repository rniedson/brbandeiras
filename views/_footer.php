<?php
$curiosidades = [
    "Você sabia que a bandeira branca só virou regra internacional de trégua na Convenção de Haia de 1899?",
    "Sabia que a bandeira do México mostra a lenda mexica da águia e da serpente sobre um nopal?",
    "Você sabia que a bandeira do Paraguai tem frente e verso diferentes?",
    "Sabia que a bandeira do Nepal é a única nacional que não é retangular?",
    "Você sabia que as bandeiras da Suíça e do Vaticano são oficialmente quadradas?",
    "Sabia que a bandeira da Líbia já foi apenas um retângulo verde sólido?",
    "Você sabia que a bandeira de Bangladesh desloca o disco para parecer centrado ao vento?",
    "Sabia que a bandeira de Palau também desloca o disco para o mastro?",
    "Você sabia que a bandeira da Arábia Saudita não vai a meio-mastro por trazer o credo islâmico?",
    "Sabia que a bandeira de Belize é uma das poucas bandeiras nacionais que incluem pessoas?",
    "Você sabia que a bandeira de Dominica usa roxo no papagaio sisserou?",
    "Sabia que a bandeira da Guatemala exibe rifles cruzados e o pássaro quetzal?",
    "Você sabia que a bandeira da República Dominicana traz uma Bíblia aberta no brasão?",
    "Sabia que a bandeira do Camboja estampa Angkor Wat em destaque?",
    "Você sabia que a bandeira do Chade é quase idêntica à bandeira da Romênia?",
    "Sabia que a bandeira de Mônaco quase se confunde com a bandeira da Indonésia?",
    "Você sabia que a bandeira da Jamaica não usa vermelho, branco ou azul?",
    "Sabia que a bandeira de Moçambique inclui um fuzil AK-47?",
    "Você sabia que a bandeira das Filipinas vira de guerra quando o vermelho fica em cima?",
    "Sabia que a bandeira da Dinamarca é a mais antiga em uso contínuo?",
    "Você sabia que a bandeira de Gales com o dragão não aparece na bandeira do Reino Unido?",
    "Sabia que a bandeira do Laos simboliza a lua cheia sobre o rio Mekong com o disco branco?",
    "Você sabia que a bandeira do Brasil mostra o céu do Rio em 15/11/1889 com constelações espelhadas?",
    "Sabia que a bandeira do Brasil dá a cada estrela um estado e o Distrito Federal?",
    "Você sabia que a bandeira da Etiópia inspirou as cores pan-africanas verde amarelo e vermelho?",
    "Sabia que as cores pan-árabes em muitas bandeiras vêm da Revolta Árabe de 1916?",
    "Você sabia que a bandeira do Afeganistão mudou muitas vezes no último século?",
    "Sabia que a bandeira do Japão só teve medidas do sol padronizadas por lei em 1999?",
    "Você sabia que a bandeira do Qatar tem proporção incomum de 11:28?",
    "Sabia que a cor vinho da bandeira do Qatar surgiu de pigmentos que escureciam no sol?",
    "Você sabia que a bandeira do Chipre traz o mapa da ilha com ramos de oliveira?",
    "Sabia que a bandeira do Alasca foi criada por um estudante de 13 anos em 1927?",
    "Você sabia que a bandeira do Brasil exibe o lema positivista abreviado 'Ordem e Progresso'?",
    "Sabia que muitas bandeiras com texto sagrado são confeccionadas em dupla face para evitar escrita espelhada?"
];

// Seleciona curiosidade inicial
$curiosidade_inicial = $curiosidades[array_rand($curiosidades)];
?>

        </main>
    </div><!-- Fim do container principal -->

   <footer class="bg-gray-900 dark:bg-gray-950 border-t border-gray-800 dark:border-gray-900 text-gray-500 dark:text-gray-400 text-xs py-2">
        <div class="container mx-auto px-4 text-center">
            © 2025 BR Bandeiras 
            <span class="text-gray-700 dark:text-gray-600">•</span> 
            v1.0.0
            <span class="text-gray-700 dark:text-gray-600">•</span>
            <span class="italic" id="curiosidade-texto"><?= htmlspecialchars($curiosidade_inicial) ?></span>
        </div>
    </footer>

    <script>
    // Array de curiosidades
    const curiosidades = <?= json_encode($curiosidades, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;

    // Elemento da curiosidade
    const curiosidadeElement = document.getElementById('curiosidade-texto');

    // Função para trocar curiosidade
    function trocarCuriosidade() {
        const index = Math.floor(Math.random() * curiosidades.length);
        curiosidadeElement.textContent = curiosidades[index];
    }

    // Trocar a cada 30 segundos
    setInterval(trocarCuriosidade, 30000);

    // ========================================
    // FUNÇÕES GLOBAIS PARA O MODAL DE PEDIDOS
    // ========================================
    
    // Função para normalizar strings (remover acentos)
    window.normalizeString = function(str) {
        return str.normalize('NFD').replace(/[\u0300-\u036f]/g, "").toLowerCase();
    }

    // Funções de formatação
    window.formatarTelefone = function(value) {
        value = value.replace(/\D/g, '');
        if (value.length > 10) {
            value = value.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
        } else if (value.length > 5) {
            value = value.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
        } else if (value.length > 2) {
            value = value.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
        }
        return value;
    }

    window.formatarCpfCnpj = function(value) {
        value = value.replace(/\D/g, '');
        if (value.length <= 11) {
            value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{2}).*/, '$1.$2.$3-$4');
        } else {
            value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2}).*/, '$1.$2.$3/$4-$5');
        }
        return value;
    }

    window.formatarDocumento = function(doc) {
        if (!doc) return '';
        doc = doc.replace(/\D/g, '');
        if (doc.length <= 11) {
            return doc.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        }
        return doc.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
    }

    // Função para o editor de texto
    window.formatText = function(command) {
        document.execCommand(command, false, null);
        const editor = document.getElementById('editor-observacoes');
        if (editor) editor.focus();
    }

    // Cache de dados para o modal
    window.modalPedidoCache = {
        clientes: null,
        produtos: null,
        loaded: false
    };

    // Pré-carregar dados após o carregamento da página (apenas em páginas de pedidos)
    <?php if (in_array(basename($_SERVER['PHP_SELF']), ['pedidos.php', 'index.php'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        // Aguardar 1 segundo após carregar a página para não interferir
        setTimeout(function() {
            // Pré-carregar dados de clientes e produtos via AJAX
            fetch('app/dados_pedido_modal.php')
                .then(response => response.json())
                .then(data => {
                    window.modalPedidoCache.clientes = data.clientes;
                    window.modalPedidoCache.produtos = data.produtos;
                    window.modalPedidoCache.loaded = true;
                    console.log('Dados do modal pré-carregados');
                })
                .catch(error => {
                    console.error('Erro ao pré-carregar dados:', error);
                });
        }, 1000);
    });
    <?php endif; ?>

    // Função para criar toast notification
    window.showNotification = function(message, type = 'info') {
        const container = document.getElementById('toast-container') || createToastContainer();
        const toast = document.createElement('div');
        toast.className = `px-4 py-3 rounded-lg shadow-lg text-white ${
            type === 'error' ? 'bg-red-500' : 
            type === 'success' ? 'bg-green-500' : 
            'bg-blue-500'
        } transform transition-all duration-300 translate-x-full`;
        toast.textContent = message;
        
        container.appendChild(toast);
        
        // Animar entrada
        setTimeout(() => {
            toast.classList.remove('translate-x-full');
        }, 10);
        
        // Remover após 3 segundos
        setTimeout(() => {
            toast.classList.add('translate-x-full', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-4 right-4 z-[60] space-y-2';
        document.body.appendChild(container);
        return container;
    }

    // Preload Alpine.js se ainda não estiver carregado
    if (!window.Alpine) {
        const alpineScript = document.createElement('link');
        alpineScript.rel = 'preload';
        alpineScript.as = 'script';
        alpineScript.href = 'https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js';
        document.head.appendChild(alpineScript);
    }
    </script>

    <!-- Preload de recursos críticos -->
    <link rel="preload" href="https://cdn.tailwindcss.com" as="script">
    <link rel="dns-prefetch" href="https://unpkg.com">
    
    <!-- CSS crítico inline para o modal (evita FOUC) -->
    <style>
        [x-cloak] { display: none !important; }
        .modal-loading { position: absolute; inset: 0; z-index: 100; background: rgba(255, 255, 255, 0.95); display: flex; align-items: center; justify-content: center; border-radius: 0.75rem; }
        .loading-spinner { width: 60px; height: 60px; border: 4px solid rgba(34, 197, 94, 0.2); border-top-color: #22c55e; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>

</body>
</html>