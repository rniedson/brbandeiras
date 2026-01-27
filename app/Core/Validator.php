<?php
/**
 * Validator - Sistema de validação centralizado
 * 
 * Fornece validações consistentes e reutilizáveis em todo o sistema.
 * 
 * @version 1.0.0
 * @date 2025-01-25
 */

namespace App\Core;

class ValidationException extends \Exception {}

class Validator {
    /**
     * Valida ID de pedido
     * 
     * @param mixed $id ID a validar
     * @return int|null ID validado ou null se inválido
     */
    public static function pedidoId($id): ?int {
        if ($id === null || $id === '') {
            return null;
        }
        
        $id = filter_var($id, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);
        
        return $id !== false ? $id : null;
    }
    
    /**
     * Valida email
     * 
     * @param string $email Email a validar
     * @return bool True se válido
     */
    public static function email(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Valida se campo é obrigatório
     * 
     * @param mixed $value Valor a validar
     * @param string $field Nome do campo (para mensagem de erro)
     * @throws ValidationException Se campo vazio
     */
    public static function required($value, string $field): void {
        if (empty($value) && $value !== '0' && $value !== 0) {
            throw new ValidationException("Campo {$field} é obrigatório");
        }
    }
    
    /**
     * Valida tamanho mínimo de string
     * 
     * @param string $value Valor a validar
     * @param int $min Tamanho mínimo
     * @param string $field Nome do campo
     * @throws ValidationException Se menor que mínimo
     */
    public static function minLength(string $value, int $min, string $field): void {
        if (strlen($value) < $min) {
            throw new ValidationException("Campo {$field} deve ter no mínimo {$min} caracteres");
        }
    }
    
    /**
     * Valida tamanho máximo de string
     * 
     * @param string $value Valor a validar
     * @param int $max Tamanho máximo
     * @param string $field Nome do campo
     * @throws ValidationException Se maior que máximo
     */
    public static function maxLength(string $value, int $max, string $field): void {
        if (strlen($value) > $max) {
            throw new ValidationException("Campo {$field} deve ter no máximo {$max} caracteres");
        }
    }
    
    /**
     * Valida CPF
     * 
     * @param string $cpf CPF a validar
     * @return bool True se válido
     */
    public static function cpf(string $cpf): bool {
        // Remove formatação
        $cpf = preg_replace('/\D/', '', $cpf);
        
        // Verifica se tem 11 dígitos
        if (strlen($cpf) != 11) {
            return false;
        }
        
        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }
        
        // Validação dos dígitos verificadores
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Valida CNPJ
     * 
     * @param string $cnpj CNPJ a validar
     * @return bool True se válido
     */
    public static function cnpj(string $cnpj): bool {
        // Remove formatação
        $cnpj = preg_replace('/\D/', '', $cnpj);
        
        // Verifica se tem 14 dígitos
        if (strlen($cnpj) != 14) {
            return false;
        }
        
        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }
        
        // Validação dos dígitos verificadores
        $length = strlen($cnpj) - 2;
        $numbers = substr($cnpj, 0, $length);
        $digits = substr($cnpj, $length);
        $sum = 0;
        $pos = $length - 7;
        
        for ($i = $length; $i >= 1; $i--) {
            $sum += $numbers[$length - $i] * $pos--;
            if ($pos < 2) {
                $pos = 9;
            }
        }
        
        $result = $sum % 11 < 2 ? 0 : 11 - $sum % 11;
        if ($result != $digits[0]) {
            return false;
        }
        
        $length = $length + 1;
        $numbers = substr($cnpj, 0, $length);
        $sum = 0;
        $pos = $length - 7;
        
        for ($i = $length; $i >= 1; $i--) {
            $sum += $numbers[$length - $i] * $pos--;
            if ($pos < 2) {
                $pos = 9;
            }
        }
        
        $result = $sum % 11 < 2 ? 0 : 11 - $sum % 11;
        if ($result != $digits[1]) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Valida CPF ou CNPJ
     * 
     * @param string $cpfCnpj CPF ou CNPJ a validar
     * @return bool True se válido
     */
    public static function cpfCnpj(string $cpfCnpj): bool {
        $limpo = preg_replace('/\D/', '', $cpfCnpj);
        
        if (strlen($limpo) == 11) {
            return self::cpf($cpfCnpj);
        } elseif (strlen($limpo) == 14) {
            return self::cnpj($cpfCnpj);
        }
        
        return false;
    }
    
    /**
     * Valida telefone brasileiro
     * 
     * @param string $telefone Telefone a validar
     * @return bool True se válido
     */
    public static function telefone(string $telefone): bool {
        $limpo = preg_replace('/\D/', '', $telefone);
        // Aceita telefone fixo (10 dígitos) ou celular (11 dígitos)
        return strlen($limpo) >= 10 && strlen($limpo) <= 11;
    }
    
    /**
     * Valida CEP brasileiro
     * 
     * @param string $cep CEP a validar
     * @return bool True se válido
     */
    public static function cep(string $cep): bool {
        $limpo = preg_replace('/\D/', '', $cep);
        return strlen($limpo) == 8;
    }
    
    /**
     * Valida valor numérico positivo
     * 
     * @param mixed $value Valor a validar
     * @param string $field Nome do campo
     * @throws ValidationException Se não for numérico ou negativo
     */
    public static function positiveNumber($value, string $field): void {
        if (!is_numeric($value) || $value < 0) {
            throw new ValidationException("Campo {$field} deve ser um número positivo");
        }
    }
    
    /**
     * Valida se valor está em lista de valores permitidos
     * 
     * @param mixed $value Valor a validar
     * @param array $allowed Valores permitidos
     * @param string $field Nome do campo
     * @throws ValidationException Se valor não está na lista
     */
    public static function in($value, array $allowed, string $field): void {
        if (!in_array($value, $allowed, true)) {
            throw new ValidationException("Campo {$field} deve ser um dos valores: " . implode(', ', $allowed));
        }
    }
}
