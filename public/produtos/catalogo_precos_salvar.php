<?php
require_once '../../app/config.php';
require_once '../../app/auth.php';
require_once '../../app/functions.php';

requireRole(['gestor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: catalogo_precos.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    $produtos_ids = $_POST['produtos'] ?? [];
    $precos = $_POST['preco'] ?? [];
    $precos_promo = $_POST['preco_promo'] ?? [];
    $custos = $_POST['custo'] ?? [];
    
    $atualizados = 0;
    
    foreach ($produtos_ids as $produto_id) {
        if (isset($precos[$produto_id])) {
            $preco = floatval($precos[$produto_id]);
            
            if ($preco > 0) {
                $stmt = $pdo->prepare("
                    UPDATE produtos_catalogo 
                    SET preco = ?, 
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                
                $stmt->execute([$preco, $produto_id]);
                $atualizados++;
            }
        }
    }
    
    // Log
    registrarLog('atualizar_precos_catalogo', "Atualizou preços de $atualizados produtos");
    
    $pdo->commit();
    
    $_SESSION['mensagem'] = "$atualizados produtos tiveram seus preços atualizados!";
    header('Location: catalogo_precos.php');
    
} catch (Exception $e) {
    $pdo->rollBack();
    
    error_log('Erro ao atualizar preços: ' . $e->getMessage());
    $_SESSION['erro'] = 'Erro ao atualizar preços';
    header('Location: catalogo_precos.php');
}