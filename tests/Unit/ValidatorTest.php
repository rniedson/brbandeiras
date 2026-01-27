<?php
/**
 * Testes unitários para Validator
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/autoload.php';

use App\Core\Validator;
use App\Core\ValidationException;

class ValidatorTest {
    private $passed = 0;
    private $failed = 0;
    
    public function run() {
        echo "=== Testes Unitários - Validator ===\n\n";
        
        $this->testPedidoId();
        $this->testEmail();
        $this->testRequired();
        $this->testCpf();
        $this->testCnpj();
        
        echo "\n=== Resultados ===\n";
        echo "Passou: {$this->passed}\n";
        echo "Falhou: {$this->failed}\n";
        echo "Total: " . ($this->passed + $this->failed) . "\n";
    }
    
    private function testPedidoId() {
        echo "Testando Validator::pedidoId()...\n";
        
        $result = Validator::pedidoId('123');
        $this->assert($result === 123, 'pedidoId("123") deve retornar 123');
        
        $result = Validator::pedidoId('abc');
        $this->assert($result === null, 'pedidoId("abc") deve retornar null');
        
        $result = Validator::pedidoId(null);
        $this->assert($result === null, 'pedidoId(null) deve retornar null');
    }
    
    private function testEmail() {
        echo "Testando Validator::email()...\n";
        
        $this->assert(Validator::email('teste@example.com'), 'Email válido deve retornar true');
        $this->assert(!Validator::email('email-invalido'), 'Email inválido deve retornar false');
        $this->assert(!Validator::email('@example.com'), 'Email sem parte local deve retornar false');
    }
    
    private function testRequired() {
        echo "Testando Validator::required()...\n";
        
        try {
            Validator::required('valor', 'campo');
            $this->assert(true, 'required com valor não deve lançar exceção');
        } catch (ValidationException $e) {
            $this->assert(false, 'required com valor não deve lançar exceção');
        }
        
        try {
            Validator::required('', 'campo');
            $this->assert(false, 'required com valor vazio deve lançar exceção');
        } catch (ValidationException $e) {
            $this->assert(true, 'required com valor vazio deve lançar exceção');
        }
    }
    
    private function testCpf() {
        echo "Testando Validator::cpf()...\n";
        
        // CPF válido (exemplo genérico)
        $cpfValido = '11144477735'; // CPF válido para testes
        $this->assert(Validator::cpf($cpfValido), 'CPF válido deve retornar true');
        
        $cpfInvalido = '12345678901';
        $this->assert(!Validator::cpf($cpfInvalido), 'CPF inválido deve retornar false');
    }
    
    private function testCnpj() {
        echo "Testando Validator::cnpj()...\n";
        
        // CNPJ válido (exemplo genérico)
        $cnpjValido = '11222333000181'; // CNPJ válido para testes
        $this->assert(Validator::cnpj($cnpjValido), 'CNPJ válido deve retornar true');
        
        $cnpjInvalido = '12345678000190';
        $this->assert(!Validator::cnpj($cnpjInvalido), 'CNPJ inválido deve retornar false');
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
    $test = new ValidatorTest();
    $test->run();
}
