<?php
// Widget de Próximas Entregas para incluir no dashboard
// Buscar próximas 5 entregas
$stmt = $pdo->prepare("
    SELECT 
        p.id,
        p.numero,
        p.prazo_entrega,
        p.status,
        p.urgente,
        c.nome as cliente_nome,
        u.nome as vendedor_nome,
        CASE 
            WHEN p.prazo_entrega < CURRENT_DATE THEN 'atrasado'
            WHEN p.prazo_entrega = CURRENT_DATE THEN 'hoje'
            WHEN p.prazo_entrega = CURRENT_DATE + INTERVAL '1 day' THEN 'amanha'
            ELSE 'futuro'
        END as prazo_status,
        EXTRACT(DAY FROM p.prazo_entrega - CURRENT_DATE) as dias_restantes
    FROM pedidos p
    LEFT JOIN clientes c ON p.cliente_id = c.id
    LEFT JOIN usuarios u ON p.vendedor_id = u.id
    WHERE 
        p.status NOT IN ('entregue', 'cancelado')
        AND p.prazo_entrega >= CURRENT_DATE - INTERVAL '7 days'
    ORDER BY 
        p.prazo_entrega,
        p.urgente DESC
    LIMIT 8
");

$stmt->execute();
$proximas_entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar entregas por período
$stmt = $pdo->query("
    SELECT 
        COUNT(CASE WHEN prazo_entrega < CURRENT_DATE THEN 1 END) as atrasadas,
        COUNT(CASE WHEN prazo_entrega = CURRENT_DATE THEN 1 END) as hoje,
        COUNT(CASE WHEN prazo_entrega BETWEEN CURRENT_DATE + INTERVAL '1 day' AND CURRENT_DATE + INTERVAL '7 days' THEN 1 END) as semana,
        COUNT(CASE WHEN prazo_entrega > CURRENT_DATE + INTERVAL '7 days' THEN 1 END) as futuras
    FROM pedidos
    WHERE status NOT IN ('entregue', 'cancelado')
");
$contadores = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!-- Widget Próximas Entregas -->
<div class="bg-white rounded-lg shadow-lg p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            Próximas Entregas
        </h3>
        <a href="calendario_entregas.php" class="text-sm text-green-600 hover:text-green-700 font-medium">
            Ver Calendário
        </a>
    </div>
    
    <!-- Contadores -->
    <div class="grid grid-cols-4 gap-2 mb-4">
        <?php if ($contadores['atrasadas'] > 0): ?>
        <div class="bg-red-50 rounded-lg p-2 text-center">
            <div class="text-xl font-bold text-red-600"><?= $contadores['atrasadas'] ?></div>
            <div class="text-xs text-red-700">Atrasadas</div>
        </div>
        <?php endif; ?>
        
        <div class="bg-green-50 rounded-lg p-2 text-center">
            <div class="text-xl font-bold text-green-600"><?= $contadores['hoje'] ?></div>
            <div class="text-xs text-green-700">Hoje</div>
        </div>
        
        <div class="bg-blue-50 rounded-lg p-2 text-center">
            <div class="text-xl font-bold text-blue-600"><?= $contadores['semana'] ?></div>
            <div class="text-xs text-blue-700">Esta Semana</div>
        </div>
        
        <div class="bg-gray-50 rounded-lg p-2 text-center">
            <div class="text-xl font-bold text-gray-600"><?= $contadores['futuras'] ?></div>
            <div class="text-xs text-gray-700">Futuras</div>
        </div>
    </div>
    
    <!-- Lista de Entregas -->
    <div class="space-y-2 max-h-96 overflow-y-auto">
        <?php if (empty($proximas_entregas)): ?>
        <p class="text-gray-500 text-center py-4">Nenhuma entrega próxima</p>
        <?php else: ?>
            <?php foreach ($proximas_entregas as $entrega): 
                $corPrazo = '';
                $textoPrazo = '';
                
                if ($entrega['prazo_status'] == 'atrasado') {
                    $corPrazo = 'bg-red-50 border-red-200';
                    $textoPrazo = abs($entrega['dias_restantes']) . ' dia(s) atraso';
                } elseif ($entrega['prazo_status'] == 'hoje') {
                    $corPrazo = 'bg-green-50 border-green-200';
                    $textoPrazo = 'HOJE';
                } elseif ($entrega['prazo_status'] == 'amanha') {
                    $corPrazo = 'bg-yellow-50 border-yellow-200';
                    $textoPrazo = 'Amanhã';
                } else {
                    $corPrazo = 'bg-gray-50 border-gray-200';
                    $textoPrazo = 'Em ' . $entrega['dias_restantes'] . ' dia(s)';
                }
                
                $corStatus = [
                    'orcamento' => 'bg-yellow-100 text-yellow-800',
                    'arte' => 'bg-purple-100 text-purple-800',
                    'producao' => 'bg-orange-100 text-orange-800',
                    'finalizado' => 'bg-blue-100 text-blue-800'
                ][$entrega['status']] ?? 'bg-gray-100 text-gray-800';
            ?>
            <a href="pedido_detalhes.php?id=<?= $entrega['id'] ?>" 
               class="block p-3 rounded-lg border hover:shadow-md transition <?= $corPrazo ?>">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center space-x-2">
                            <span class="font-semibold text-sm">#<?= $entrega['numero'] ?></span>
                            <?php if ($entrega['urgente']): ?>
                            <span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-800 font-medium">
                                URGENTE
                            </span>
                            <?php endif; ?>
                            <span class="px-2 py-0.5 text-xs rounded-full <?= $corStatus ?>">
                                <?= ucfirst($entrega['status']) ?>
                            </span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1 truncate">
                            <?= htmlspecialchars($entrega['cliente_nome']) ?>
                        </p>
                        <p class="text-xs text-gray-500">
                            Vendedor: <?= htmlspecialchars($entrega['vendedor_nome']) ?>
                        </p>
                    </div>
                    <div class="text-right ml-2">
                        <p class="text-xs font-semibold <?= $entrega['prazo_status'] == 'atrasado' ? 'text-red-600' : 'text-gray-700' ?>">
                            <?= $textoPrazo ?>
                        </p>
                        <p class="text-xs text-gray-500">
                            <?= formatarData($entrega['prazo_entrega']) ?>
                        </p>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php if (count($proximas_entregas) >= 8): ?>
    <div class="mt-4 text-center">
        <a href="calendario_entregas.php" class="text-sm text-green-600 hover:text-green-700 font-medium">
            Ver todas as entregas →
        </a>
    </div>
    <?php endif; ?>
</div>