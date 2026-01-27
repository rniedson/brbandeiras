<?php
/**
 * Testes unitários para funções auxiliares
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/functions.php';

class FunctionsTest {
    private $passed = 0;
    private $failed = 0;
    
    public function run() {
        echo "=== Testes Unitários - Funções Auxiliares ===\n\n";
        
        $this->testValidarPedidoId();
        $this->testFormatarMoeda();
        $this->testFormatarData();
        $this->testFormatarCpfCnpj();
        
        echo "\n=== Resultados ===\n";
        echo "Passou: {$this->passed}\n";
        echo "Falhou: {$this->failed}\n";
        echo "Total: " . ($this->passed + $this->failed) . "\n";
    }
    
    private function testValidarPedidoId() {
        echo "Testando validarPedidoId()...\n";
        
        // Teste com ID válido
        $result = validarPedidoId('123');
        $this->assert($result === 123, 'validarPedidoId("123") deve retornar 123');
        
        // Teste com ID inválido
        $result = validarPedidoId('abc');
        $this->assert($result === null, 'validarPedidoId("abc") deve retornar null');
        
        // Teste com null
        $result = validarPedidoId(null);
        $this->assert($result === null, 'validarPedidoId(null) deve retornar null');
        
        // Teste com zero
        $result = validarPedidoId('0');
        $this->assert($result === null, 'validarPedidoId("0") deve retornar null');
    }
    
    private function testFormatarMoeda() {
        echo "Testando formatarMoeda()...\n";
        
        $result = formatarMoeda(1234.56);
        $this->assert($result === 'R$ 1.234,56', 'formatarMoeda(1234.56) deve retornar "R$ 1.234,56"');
        
        $result = formatarMoeda(0);
        $this->assert($result === 'R$ 0,00', 'formatarMoeda(0) deve retornar "R$ 0,00"');
        
        $result = formatarMoeda(null);
        $this->assert($result === 'R$ 0,00', 'formatarMoeda(null) deve retornar "R$ 0,00"');
    }
    
    private function testFormatarData() {
        echo "Testando formatarData()...\n";
        
        $result = formatarData('2025-01-25');
        $this->assert($result === '25/01/2025', 'formatarData("2025-01-25") deve retornar "25/01/2025"');
        
        $result = formatarData('2025-01-25 14:30:00', 'd/m/Y H:i');
        $this->assert($result === '25/01/2025 14:30', 'formatarData com formato customizado');
        
        $result = formatarData(null);
        $this->assert($result === '', 'formatarData(null) deve retornar string vazia');
    }
    
    private function testFormatarCpfCnpj() {
        echo "Testando formatarCpfCnpj()...\n";
        
        // CPF
        $result = formatarCpfCnpj('12345678901');
        $this->assert(strlen($result) > 0, 'formatarCpfCnpj deve formatar CPF');
        
        // CNPJ
        $result = formatarCpfCnpj('12345678000190');
        $this->assert(strlen($result) > 0, 'formatarCpfCnpj deve formatar CNPJ');
    }
    
    private function assert($condition, $message) {
        if ($condition) {
            $this->passed++;
            echo "  ✓ {$message}\n";
        } else {
            $this->failed++;
            echo "  ✗ {$message}\n";
        }
    }
}

// Executar testes se chamado diretamente
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $test = new FunctionsTest();
    $test->run();
}
