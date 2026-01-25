# BR Bandeiras - Sistema de Gestão

Sistema completo de gestão para produção de bandeiras.

## 📚 Documentação

Toda a documentação está organizada em `docs/`:

- **[Documentação Completa](docs/README.md)** - Índice principal da documentação
- **[Instalação](docs/INSTALACAO.md)** - Guia de instalação
- **[Configuração](docs/CONFIGURACAO.md)** - Configuração do sistema
- **[Arquitetura](docs/ARQUITETURA.md)** - Arquitetura e padrões

## 🚀 Início Rápido

1. **Instalação**: Siga o guia em [docs/INSTALACAO.md](docs/INSTALACAO.md)
2. **Configuração**: Configure o ambiente em [docs/CONFIGURACAO.md](docs/CONFIGURACAO.md)
3. **Acesso**: Acesse `http://localhost/brbandeiras/public/`

## 📁 Estrutura do Projeto

```
brbandeiras/
├── app/              # Backend/Core
├── public/           # Frontend (organizado por módulos)
├── views/            # Templates
├── docs/             # Documentação
├── scripts/          # Scripts utilitários
├── tests/            # Testes
└── storage/          # Arquivos gerados
```

## 🔧 Requisitos

- PHP 8.0+ (recomendado: PHP 8.5+ via Homebrew)
- PostgreSQL 12+
- Apache com mod_rewrite
- Extensões PHP: pdo_pgsql, mbstring, json

## 📖 Guias Rápidos

- **Apache**: [docs/guias/apache.md](docs/guias/apache.md)
- **PHP**: [docs/guias/php.md](docs/guias/php.md)
- **Banco de Dados**: [docs/guias/banco-dados.md](docs/guias/banco-dados.md)

## 🐛 Troubleshooting

Problemas comuns e soluções em [docs/troubleshooting/problemas-comuns.md](docs/troubleshooting/problemas-comuns.md)

## 📝 Desenvolvimento

- **Fase 1 MVC**: [docs/desenvolvimento/fase1-implementacao.md](docs/desenvolvimento/fase1-implementacao.md)
- **Refatoração**: [docs/desenvolvimento/refatoracao.md](docs/desenvolvimento/refatoracao.md)

## 🔗 Links Úteis

- [Scripts de Instalação](scripts/install/)
- [Testes](tests/)
- [Documentação Completa](docs/)

## 📄 Licença

[Informações de licença]
