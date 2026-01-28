# Credenciais de Login - BR Bandeiras

## 🔐 Usuário Administrador Padrão

Após a criação da tabela `usuarios`, foi criado um usuário administrador padrão:

### Credenciais:

- **Email:** `admin@brbandeiras.com.br`
- **Senha:** `admin123`
- **Perfil:** `admin`

## ⚠️ IMPORTANTE - Segurança

**ALTERE A SENHA IMEDIATAMENTE após o primeiro login!**

Esta é uma senha padrão e deve ser alterada para garantir a segurança do sistema.

## 📋 Como Alterar a Senha

Após fazer login, você pode alterar a senha através da interface do sistema ou diretamente no banco de dados:

### Via Banco de Dados:

```sql
-- Conectar ao banco
psql -d brbandeiras -U brbandeiras_user

-- Atualizar senha (substitua 'nova_senha_segura' pela senha desejada)
UPDATE usuarios 
SET senha = '$2y$10$[hash_gerado_pelo_password_hash]' 
WHERE email = 'admin@brbandeiras.com.br';
```

### Via PHP (gerar hash):

```php
<?php
require 'app/config.php';

$nova_senha = 'sua_nova_senha_segura';
$hash = password_hash($nova_senha, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE email = ?");
$stmt->execute([$hash, 'admin@brbandeiras.com.br']);

echo "Senha alterada com sucesso!";
```

## 🔍 Verificar Usuários no Banco

```bash
# Via PostgreSQL
psql -d brbandeiras -U brbandeiras_user -c "SELECT id, nome, email, perfil, ativo FROM usuarios;"

# Via PHP
php -r "require 'app/config.php'; \$stmt = \$pdo->query('SELECT id, nome, email, perfil FROM usuarios'); \$usuarios = \$stmt->fetchAll(); foreach (\$usuarios as \$u) echo \$u['nome'] . ' - ' . \$u['email'] . PHP_EOL;"
```

## 👥 Criar Novos Usuários

### Via SQL:

```sql
INSERT INTO usuarios (nome, email, senha, perfil, ativo) 
VALUES (
    'Nome do Usuário',
    'email@brbandeiras.com.br',
    '$2y$10$[hash_gerado]',
    'vendedor',  -- ou 'gestor', 'arte', 'producao', 'financeiro', 'admin'
    true
);
```

### Via PHP:

```php
<?php
require 'app/config.php';

$nome = 'Nome do Usuário';
$email = 'email@brbandeiras.com.br';
$senha = 'senha123';
$perfil = 'vendedor'; // gestor, vendedor, arte, producao, financeiro, admin

$hash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, perfil, ativo) VALUES (?, ?, ?, ?, true)");
$stmt->execute([$nome, $email, $hash, $perfil]);

echo "Usuário criado com sucesso!";
```

## 🔑 Perfis Disponíveis

- **admin** - Acesso total ao sistema
- **gestor** - Gestão e administração
- **vendedor** - Vendas e pedidos
- **arte** - Arte e design
- **producao** - Produção
- **financeiro** - Financeiro

## ✅ Teste de Login

Após criar/atualizar usuário, teste o login:

```php
<?php
require 'app/config.php';

$email = 'admin@brbandeiras.com.br';
$senha = 'admin123';

$stmt = $pdo->prepare("SELECT id, nome, email, senha, perfil FROM usuarios WHERE email = ? AND ativo = true");
$stmt->execute([$email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if ($usuario && password_verify($senha, $usuario['senha'])) {
    echo "✓ Login OK! Usuário: " . $usuario['nome'];
} else {
    echo "✗ Login falhou";
}
```

## 🚨 Troubleshooting

### Erro: "E-mail ou senha inválidos"

1. Verifique se o usuário existe:
   ```sql
   SELECT * FROM usuarios WHERE email = 'seu@email.com';
   ```

2. Verifique se o usuário está ativo:
   ```sql
   SELECT * FROM usuarios WHERE email = 'seu@email.com' AND ativo = true;
   ```

3. Verifique o hash da senha:
   ```php
   $hash = password_hash('sua_senha', PASSWORD_DEFAULT);
   echo $hash;
   ```

### Erro: "Tabela usuarios não existe"

Execute o script de criação:
```bash
su - postgres -c "psql -d brbandeiras -f /tmp/criar_usuarios_completo.sql"
```

### Resetar Senha do Administrador

```sql
-- Gerar novo hash (execute no PHP primeiro para gerar o hash)
UPDATE usuarios 
SET senha = '$2y$10$[novo_hash_aqui]' 
WHERE email = 'admin@brbandeiras.com.br';
```
