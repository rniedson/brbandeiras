<?php
// Configurar duração da sessão para 2 horas (7200 segundos)
// Reduzido de 14h para melhorar segurança
ini_set('session.gc_maxlifetime', 7200);
session_set_cookie_params(7200);

require_once '../app/config.php';

// Se já logado, redireciona
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Mensagens
$mensagem = $_SESSION['mensagem'] ?? null;
$erro = $_SESSION['erro'] ?? null;
unset($_SESSION['mensagem'], $_SESSION['erro']);

// Array de curiosidades sobre bandeiras
$curiosidades = [
    "Você sabia que a bandeira branca só virou regra internacional de trégua na Convenão de Haia de 1899?",
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

// Seleciona curiosidade aleatória para o loading
$curiosidade_loading = $curiosidades[array_rand($curiosidades)];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>BR Bandeiras - Portal de Acesso</title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

  <style>
    /* BR Bandeiras - Sistema de Login
       Versão: 3.2 - Com localStorage para email e sessão estendida
       Características:
       - Loading inicial de 3s com curiosidade sobre bandeiras
       - Background dinâmico com rotação de imagens
       - Overlay escuro para contraste
       - Blur effect cinematográfico
       - Animações suaves e profissionais
       - Design totalmente responsivo
       - Layout mobile otimizado sem seção de departamentos
       - Email salvo no localStorage após primeiro uso
       - Sessão de 14 horas
    */
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      /* Cores baseadas no dashboard */
      --verde-escuro: #0d2818;
      --verde-medio: #1e5e3f;
      --verde-claro: #2d7d57;
      --amarelo-principal: #f5b800;
      --amarelo-hover: #ffd43b;
      --cinza-escuro: #1a1a1a;
      --cinza-medio: #2d2d2d;
      --cinza-claro: #f5f5f5;
      --branco: #ffffff;
    }

    html, body {
      height: 100%;
      overflow-x: hidden;
    }

    body {
      font-family: 'Inter', system-ui, sans-serif;
      color: var(--cinza-escuro);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      padding: 1rem;
    }

    /* Loading inicial */
    .initial-loading {
      position: fixed;
      inset: 0;
      background: var(--verde-escuro);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      transition: opacity 0.8s ease-out, visibility 0.8s ease-out;
    }

    .initial-loading.fade-out {
      opacity: 0;
      visibility: hidden;
    }

    .loading-content {
      text-align: center;
      animation: loadingPulse 1.5s ease-in-out infinite;
      max-width: 500px;
      padding: 0 1.5rem;
    }

    @keyframes loadingPulse {
      0%, 100% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.05); opacity: 0.8; }
    }

    .loading-logo {
      margin-bottom: 2rem;
    }

    .loading-bar {
      width: 4px;
      height: 60px;
      background: var(--amarelo-principal);
      display: inline-block;
      margin-right: 1rem;
      box-shadow: 0 0 30px rgba(245, 184, 0, 0.8);
      animation: loadingBarGlow 1.5s ease-in-out infinite;
    }

    @keyframes loadingBarGlow {
      0%, 100% { box-shadow: 0 0 30px rgba(245, 184, 0, 0.8); }
      50% { box-shadow: 0 0 50px rgba(245, 184, 0, 1); }
    }

    .loading-title {
      font-size: 2rem;
      font-weight: 800;
      color: var(--branco);
      letter-spacing: -0.02em;
      display: inline-block;
      vertical-align: top;
      margin-top: 10px;
    }

    .loading-spinner {
      width: 40px;
      height: 40px;
      border: 3px solid rgba(255, 255, 255, 0.2);
      border-top-color: var(--amarelo-principal);
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin: 0 auto;
    }

    .loading-text {
      color: rgba(255, 255, 255, 0.8);
      font-size: 0.875rem;
      margin-top: 1rem;
      font-weight: 500;
    }

    /* Curiosidade no loading */
    .loading-curiosity {
      margin-top: 2rem;
      padding: 1rem;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 8px;
      border: 1px solid rgba(245, 184, 0, 0.2);
    }

    .loading-curiosity-icon {
      display: inline-block;
      width: 18px;
      height: 18px;
      margin-right: 0.5rem;
      vertical-align: middle;
      color: var(--amarelo-principal);
    }

    .loading-curiosity-text {
      font-size: 0.8125rem;
      color: rgba(255, 255, 255, 0.9);
      font-style: italic;
      line-height: 1.5;
    }

    /* Background com imagens */
    .bg-image {
      position: fixed;
      inset: -10%;
      width: 120%;
      height: 120%;
      z-index: -2;
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      filter: blur(20px);
      transition: opacity 2s ease-in-out;
      will-change: opacity;
      animation: slowZoom 30s ease-in-out infinite alternate;
    }

    @keyframes slowZoom {
      0% { transform: scale(1) translate(0, 0); }
      100% { transform: scale(1.1) translate(-2%, -2%); }
    }

    .bg-image.active {
      opacity: 1;
    }

    .bg-image.inactive {
      opacity: 0;
    }

    /* Overlay escuro sobre as imagens */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background: rgba(4, 25, 2, 0.8);
      z-index: -1;
      pointer-events: none;
    }

    /* Container principal */
    .login-container {
      position: relative;
      width: 100%;
      max-width: 420px;
      background: rgba(255, 255, 255, 1);
      border-radius: 16px;
      box-shadow: 
        0 30px 90px rgba(0, 0, 0, 0.7),
        0 0 150px rgba(245, 184, 0, 0.15),
        inset 0 0 0 1px rgba(255, 255, 255, 0.9);
      overflow: hidden;
      animation: fadeInUp 0.8s ease-out;
      max-height: calc(100vh - 2rem);
      overflow-y: auto;
      opacity: 0;
      animation-delay: 0.3s;
      animation-fill-mode: forwards;
    }

    /* Scrollbar customizada para o container */
    .login-container::-webkit-scrollbar {
      width: 6px;
    }

    .login-container::-webkit-scrollbar-track {
      background: rgba(0, 0, 0, 0.1);
    }

    .login-container::-webkit-scrollbar-thumb {
      background: var(--verde-medio);
      border-radius: 3px;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Header com logo */
    .login-header {
      background: linear-gradient(135deg, var(--verde-escuro) 0%, var(--verde-medio) 100%);
      padding: 2rem 1.5rem;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .login-header::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(245, 184, 0, 0.1) 0%, transparent 60%);
      animation: rotate 30s linear infinite;
    }

    @keyframes rotate {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    .logo-wrapper {
      position: relative;
      display: inline-block;
      margin-bottom: 0.5rem;
    }

    .logo-bar {
      width: 4px;
      height: 40px;
      background: var(--amarelo-principal);
      display: inline-block;
      margin-right: 0.75rem;
      box-shadow: 0 0 20px rgba(245, 184, 0, 0.5);
    }

    .logo-text {
      display: inline-block;
      vertical-align: top;
    }

    .logo-title {
      font-size: 1.75rem;
      font-weight: 800;
      color: var(--branco);
      letter-spacing: -0.02em;
      margin: 0;
      text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    }

    .logo-subtitle {
      font-size: 0.875rem;
      font-weight: 400;
      color: rgba(255, 255, 255, 0.9);
      margin-top: 0.25rem;
    }

    /* Corpo do formulário */
    .login-body {
      padding: 1.5rem;
      background: var(--branco);
    }

    /* Alertas */
    .alert {
      padding: 0.75rem;
      border-radius: 8px;
      margin-bottom: 1.25rem;
      font-size: 0.875rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      animation: slideIn 0.3s ease-out;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateX(-10px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    .alert-success {
      background: #d1fae5;
      color: #065f46;
      border: 1px solid #a7f3d0;
    }

    .alert-error {
      background: #fee2e2;
      color: #991b1b;
      border: 1px solid #fecaca;
    }

    /* Formulário */
    .form-group {
      margin-bottom: 1.25rem;
    }

    .form-label {
      display: block;
      font-size: 0.875rem;
      font-weight: 600;
      color: #374151;
      margin-bottom: 0.5rem;
    }

    .input-wrapper {
      position: relative;
    }

    .input-icon {
      position: absolute;
      left: 0.875rem;
      top: 50%;
      transform: translateY(-50%);
      width: 18px;
      height: 18px;
      color: #9ca3af;
      pointer-events: none;
      transition: color 0.3s;
    }

    .form-input {
      width: 100%;
      padding: 0.625rem 0.875rem 0.625rem 2.75rem;
      background: #f9fafb;
      border: 2px solid #e5e7eb;
      border-radius: 8px;
      font-size: 1rem;
      color: #111827;
      transition: all 0.3s;
    }

    .form-input:hover {
      border-color: #d1d5db;
    }

    .form-input:focus {
      outline: none;
      border-color: var(--verde-medio);
      background: var(--branco);
      box-shadow: 0 0 0 3px rgba(30, 94, 63, 0.1);
    }

    .form-input:focus ~ .input-icon {
      color: var(--verde-medio);
    }

    .form-input::placeholder {
      color: #9ca3af;
    }

    /* Toggle de senha */
    .password-toggle {
      position: absolute;
      right: 0.875rem;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #9ca3af;
      cursor: pointer;
      padding: 0.25rem;
      transition: color 0.3s;
    }

    .password-toggle:hover {
      color: var(--verde-medio);
    }

    /* Checkbox */
    .remember-section {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.25rem;
      flex-wrap: wrap;
      gap: 0.5rem;
    }

    .checkbox-wrapper {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .checkbox-input {
      width: 16px;
      height: 16px;
      accent-color: var(--verde-medio);
      cursor: pointer;
    }

    .checkbox-label {
      font-size: 0.875rem;
      color: #6b7280;
      cursor: pointer;
      user-select: none;
    }

    .forgot-link {
      font-size: 0.875rem;
      color: var(--verde-medio);
      text-decoration: none;
      font-weight: 500;
      transition: color 0.3s;
    }

    .forgot-link:hover {
      color: var(--verde-claro);
      text-decoration: underline;
    }

    /* Botão de submit */
    .submit-btn {
      width: 100%;
      padding: 0.75rem;
      background: var(--amarelo-principal);
      border: none;
      border-radius: 8px;
      color: var(--cinza-escuro);
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      position: relative;
      overflow: hidden;
    }

    .submit-btn::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.3);
      transform: translate(-50%, -50%);
      transition: width 0.6s, height 0.6s;
    }

    .submit-btn:hover {
      background: var(--amarelo-hover);
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(245, 184, 0, 0.3);
    }

    .submit-btn:active {
      transform: translateY(0);
    }

    .submit-btn:active::before {
      width: 300px;
      height: 300px;
    }

    .submit-btn.loading {
      color: transparent;
      pointer-events: none;
    }

    .submit-btn.loading::after {
      content: '';
      position: absolute;
      width: 20px;
      height: 20px;
      top: 50%;
      left: 50%;
      margin: -10px 0 0 -10px;
      border: 2px solid rgba(0, 0, 0, 0.3);
      border-top-color: var(--cinza-escuro);
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* Showcase de departamentos - Esconder em mobile */
    .dept-showcase {
      padding: 1.25rem 0 0;
      margin-top: 1.25rem;
      border-top: 1px solid #e5e7eb;
    }

    .dept-info {
      font-size: 0.75rem;
      color: #6b7280;
      text-align: center;
      margin-bottom: 0.875rem;
    }

    .dept-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 0.5rem;
    }

    .dept-item {
      padding: 0.625rem 0.375rem;
      text-align: center;
      border-radius: 6px;
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      transition: all 0.5s;
      position: relative;
      overflow: hidden;
    }

    .dept-item::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, var(--verde-medio), var(--amarelo-principal));
      opacity: 0;
      transition: opacity 0.5s;
    }

    .dept-item.active {
      background: var(--verde-medio);
      border-color: var(--verde-medio);
      transform: scale(1.05);
    }

    .dept-item.active::before {
      opacity: 0.1;
    }

    .dept-icon {
      font-size: 1.25rem;
      margin-bottom: 0.25rem;
      display: block;
      transition: transform 0.5s;
    }

    .dept-item.active .dept-icon {
      transform: scale(1.2);
    }

    .dept-name {
      font-size: 0.625rem;
      font-weight: 600;
      color: #6b7280;
      transition: color 0.5s;
    }

    .dept-item.active .dept-name {
      color: var(--branco);
    }

    /* Badges de segurança */
    .security-badges {
      display: flex;
      justify-content: center;
      gap: 1rem;
      margin-top: 1.25rem;
      padding-top: 1.25rem;
      border-top: 1px solid #e5e7eb;
    }

    .security-badge {
      display: flex;
      align-items: center;
      gap: 0.375rem;
      font-size: 0.625rem;
      color: #6b7280;
    }

    .security-badge svg {
      width: 14px;
      height: 14px;
      color: var(--verde-medio);
    }

    /* Indicadores de imagem ativa */
    .bg-indicators {
      position: fixed;
      bottom: 10px;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      gap: 6px;
      z-index: 10;
    }

    .bg-indicator {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.3);
      transition: all 0.3s ease;
    }

    .bg-indicator.active {
      background: var(--amarelo-principal);
      transform: scale(1.2);
    }

    /* Media Queries para Responsividade */
    @media (max-width: 640px) {
      body {
        padding: 0;
        align-items: stretch;
      }

      .login-container {
        max-width: 100%;
        width: 100%;
        height: 100vh;
        border-radius: 0;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        display: flex;
        flex-direction: column;
      }

      .login-header {
        padding: 2rem 1.5rem;
        flex-shrink: 0;
      }

      .logo-bar {
        width: 3px;
        height: 35px;
        margin-right: 0.5rem;
      }

      .logo-title {
        font-size: 1.5rem;
      }

      .logo-subtitle {
        font-size: 0.75rem;
      }

      .login-body {
        padding: 2rem 1.5rem;
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
      }

      .form-group {
        margin-bottom: 1.5rem;
      }

      .form-input {
        padding: 1rem 1rem 1rem 3rem;
        font-size: 16px; /* Previne zoom no iOS */
      }

      .input-icon {
        width: 18px;
        height: 18px;
        left: 1rem;
      }

      .password-toggle {
        right: 1rem;
      }

      .submit-btn {
        padding: 1rem;
        font-size: 1rem;
        margin-top: 0.5rem;
      }

      /* REMOVER completamente a seção de departamentos em mobile */
      .dept-showcase {
        display: none !important;
      }

      .security-badges {
        flex-direction: row;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.75rem;
        margin-top: 1rem;
        padding-top: 1rem;
      }

      .security-badge {
        font-size: 0.625rem;
      }

      .security-badge svg {
        width: 12px;
        height: 12px;
      }

      .remember-section {
        font-size: 0.875rem;
        margin-bottom: 1.5rem;
      }

      .checkbox-label,
      .forgot-link {
        font-size: 0.875rem;
      }

      .alert {
        padding: 0.875rem 1rem;
        font-size: 0.875rem;
        margin-bottom: 1.5rem;
      }

      .alert svg {
        width: 18px;
        height: 18px;
      }

      .bg-indicators {
        bottom: 5px;
        gap: 4px;
      }

      .bg-indicator {
        width: 5px;
        height: 5px;
      }

      /* Loading inicial mobile */
      .loading-bar {
        height: 50px;
        width: 3px;
      }

      .loading-title {
        font-size: 1.75rem;
      }

      .loading-spinner {
        width: 35px;
        height: 35px;
      }

      .loading-text {
        font-size: 0.8125rem;
      }

      .loading-curiosity {
        margin-top: 1.5rem;
        padding: 0.875rem;
      }

      .loading-curiosity-icon {
        width: 16px;
        height: 16px;
      }

      .loading-curiosity-text {
        font-size: 0.75rem;
      }
    }

    @media (max-width: 375px) {
      .login-header {
        padding: 1.75rem 1.25rem;
      }

      .logo-title {
        font-size: 1.375rem;
      }

      .logo-subtitle {
        font-size: 0.6875rem;
      }

      .login-body {
        padding: 1.75rem 1.25rem;
      }

      .form-input {
        padding: 0.875rem 0.875rem 0.875rem 2.75rem;
      }

      .submit-btn {
        padding: 0.875rem;
        font-size: 0.9375rem;
      }
    }

    /* Animação de entrada dos departamentos (somente desktop) */
    @media (min-width: 641px) {
      .dept-item {
        opacity: 0;
        animation: deptFadeIn 0.5s ease-out forwards;
      }

      .dept-item:nth-child(1) { animation-delay: 0.1s; }
      .dept-item:nth-child(2) { animation-delay: 0.2s; }
      .dept-item:nth-child(3) { animation-delay: 0.3s; }
      .dept-item:nth-child(4) { animation-delay: 0.4s; }

      @keyframes deptFadeIn {
        from {
          opacity: 0;
          transform: translateY(10px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }
    }

    /* Loading overlay do formulário */
    .loading-overlay {
      position: fixed;
      inset: 0;
      background: rgba(13, 40, 24, 0.95);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1000;
    }

    .loading-overlay.active {
      display: flex;
    }

    .loader {
      width: 50px;
      height: 50px;
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-top-color: var(--amarelo-principal);
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    /* Ajustes para teclado virtual no mobile */
    @media (max-height: 600px) and (max-width: 640px) {
      .login-container {
        justify-content: flex-start;
      }

      .login-header {
        padding: 1.5rem 1.25rem;
      }

      .login-body {
        padding: 1.5rem 1.25rem;
      }

      .security-badges {
        display: none;
      }
    }

    /* Mobile em modo paisagem */
    @media (max-height: 500px) and (orientation: landscape) {
      .login-container {
        max-width: 500px;
        margin: 0 auto;
        height: 100vh;
      }

      .login-header {
        padding: 1rem 1.5rem;
      }

      .logo-bar {
        height: 25px;
        width: 3px;
      }

      .logo-title {
        font-size: 1.25rem;
      }

      .logo-subtitle {
        display: none;
      }

      .form-group {
        margin-bottom: 1rem;
      }

      .security-badges {
        margin-top: 0.5rem;
        padding-top: 0.5rem;
      }
    }
  </style>
</head>
<body>
  <!-- Loading Inicial -->
  <div class="initial-loading" id="initialLoading">
    <div class="loading-content">
      <div class="loading-logo">
        <span class="loading-bar"></span>
        <span class="loading-title">BR BANDEIRAS</span>
      </div>
      <div class="loading-spinner"></div>
      <p class="loading-text">Preparando o sistema...</p>
      
      <!-- Curiosidade no Loading -->
      <div class="loading-curiosity">
        <svg class="loading-curiosity-icon" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        <span class="loading-curiosity-text"><?= htmlspecialchars($curiosidade_loading) ?></span>
      </div>
    </div>
  </div>

  <!-- Sistema de Background Dinâmico -->
  <!-- Duas camadas que alternam para transição suave -->
  <div class="bg-image active" id="bg1" style="background-image: url('../uploads/background/03.jpg')"></div>
  <div class="bg-image inactive" id="bg2"></div>

  <!-- Indicadores de Background -->
  <div class="bg-indicators">
    <span class="bg-indicator active"></span>
    <span class="bg-indicator"></span>
    <span class="bg-indicator"></span>
    <span class="bg-indicator"></span>
    <span class="bg-indicator"></span>
  </div>

  <!-- Container Principal -->
  <main class="login-container">
    <!-- Header -->
    <header class="login-header">
      <div class="logo-wrapper">
        <span class="logo-bar"></span>
        <div class="logo-text">
          <h1 class="logo-title">BR BANDEIRAS</h1>
          <p class="logo-subtitle">Gestão de Fabricação de Bandeiras</p>
        </div>
      </div>
    </header>

    <!-- Corpo do Formulário -->
    <div class="login-body">
      <!-- Mensagens PHP -->
      <?php if ($mensagem): ?>
        <div class="alert alert-success">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
          </svg>
          <?= htmlspecialchars($mensagem) ?>
        </div>
      <?php endif; ?>

      <?php if ($erro): ?>
        <div class="alert alert-error">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
          </svg>
          <?= htmlspecialchars($erro) ?>
        </div>
      <?php endif; ?>

      <!-- Formulário -->
      <form id="loginForm" method="POST" action="auth.php">
        <?= CSRF::getField() ?>
        <!-- Email -->
        <div class="form-group">
          <label for="email" class="form-label">E-mail</label>
          <div class="input-wrapper">
            <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor">
              <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
              <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
            </svg>
            <input 
              type="email" 
              id="email" 
              name="email" 
              class="form-input" 
              placeholder="seu@brbandeiras.com.br"
              required
              autocomplete="email"
            >
          </div>
        </div>

        <!-- Senha -->
        <div class="form-group">
          <label for="password" class="form-label">Senha</label>
          <div class="input-wrapper">
            <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
            </svg>
            <input 
              type="password" 
              id="password" 
              name="senha" 
              class="form-input" 
              placeholder="••••••••"
              required
              autocomplete="current-password"
            >
            <button type="button" class="password-toggle" id="passwordToggle">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Lembrar e Esqueceu -->
        <div class="remember-section">
          <div class="checkbox-wrapper">
            <input type="checkbox" id="remember" name="remember" class="checkbox-input">
            <label for="remember" class="checkbox-label">Lembrar de mim</label>
          </div>
          <a href="#" class="forgot-link">Esqueceu a senha?</a>
        </div>

        <!-- Botão Submit -->
        <button type="submit" class="submit-btn" id="submitBtn">
          Entrar
        </button>

        <!-- Badges de Segurança -->
        <div class="security-badges">
          <div class="security-badge">
            <svg viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            Conexão Segura
          </div>
          <div class="security-badge">
            <svg viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M18 8a6 6 0 01-7.743 5.743L10 14l-1 1-1 1H6v2H2v-4l4.257-4.257A6 6 0 1118 8zm-6-4a1 1 0 100 2 2 2 0 012 2 1 1 0 102 0 4 4 0 00-4-4z" clip-rule="evenodd"/>
            </svg>
            Dados Criptografados
          </div>
        </div>

        <!-- Showcase de Departamentos (apenas desktop) -->
        <div class="dept-showcase">
          <p class="dept-info">Seu departamento será identificado automaticamente pelo sistema</p>
          <div class="dept-grid">
            <div class="dept-item" data-dept="gestao">
              <span class="dept-icon">👔</span>
              <span class="dept-name">Gestão</span>
            </div>
            <div class="dept-item" data-dept="vendas">
              <span class="dept-icon">💼</span>
              <span class="dept-name">Vendas</span>
            </div>
            <div class="dept-item" data-dept="arte">
              <span class="dept-icon">🎨</span>
              <span class="dept-name">Artes</span>
            </div>
            <div class="dept-item" data-dept="producao">
              <span class="dept-icon">🏭</span>
              <span class="dept-name">Produção</span>
            </div>
          </div>
        </div>
      </form>
    </div>
  </main>

  <!-- Loading Overlay -->
  <div class="loading-overlay" id="loadingOverlay">
    <div class="loader"></div>
  </div>

  <script>
    // Elementos
    const deptItems = document.querySelectorAll('.dept-item');
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.getElementById('passwordToggle');
    const form = document.getElementById('loginForm');
    const submitBtn = document.getElementById('submitBtn');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const initialLoading = document.getElementById('initialLoading');
    const emailInput = document.getElementById('email');

    // ========== FUNCIONALIDADE DE EMAIL NO LOCALSTORAGE ==========
    // Chave para armazenar no localStorage
    const STORAGE_KEY = 'brBandeirasEmail';
    
    // Carregar email salvo quando a página carregar
    window.addEventListener('DOMContentLoaded', () => {
      const savedEmail = localStorage.getItem(STORAGE_KEY);
      if (savedEmail) {
        emailInput.value = savedEmail;
        // Focar direto no campo de senha se já tem email
        passwordInput.focus();
      } else {
        // Caso contrário, focar no campo de email
        emailInput.focus();
      }
    });

    // Salvar email quando o usuário digitar
    let emailSaved = false;
    emailInput.addEventListener('blur', () => {
      const email = emailInput.value.trim();
      
      // Só salva se for um email válido com @brbandeiras.com.br
      if (email && email.includes('@') && !emailSaved) {
        localStorage.setItem(STORAGE_KEY, email);
        emailSaved = true;
      }
    });

    // Também salvar ao submeter o formulário (para garantir)
    form.addEventListener('submit', (e) => {
      const email = emailInput.value.trim();
      if (email && email.includes('@')) {
        localStorage.setItem(STORAGE_KEY, email);
      }
    });

    // Loading inicial de 3 segundos
    window.addEventListener('load', () => {
      setTimeout(() => {
        initialLoading.classList.add('fade-out');
      }, 3000);
    });

    // Rotação automática dos departamentos (apenas desktop)
    if (window.innerWidth > 640) {
      let currentDept = 0;
      function rotateDepartments() {
        deptItems.forEach(item => item.classList.remove('active'));
        deptItems[currentDept].classList.add('active');
        currentDept = (currentDept + 1) % deptItems.length;
      }

      // Iniciar rotação
      setInterval(rotateDepartments, 2000);
      rotateDepartments();
    }

    // Toggle de senha
    let showPassword = false;
    passwordToggle.addEventListener('click', () => {
      showPassword = !showPassword;
      passwordInput.type = showPassword ? 'text' : 'password';
      
      passwordToggle.innerHTML = showPassword ? 
        '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.479-5.479z" clip-rule="evenodd"/><path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z"/></svg>' :
        '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>';
    });

    // Submit do formulário (mantém funcionalidade original)
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      
      // Adicionar classe loading ao botão
      submitBtn.classList.add('loading');
      submitBtn.disabled = true;
      
      // Mostrar overlay após pequeno delay
      setTimeout(() => {
        loadingOverlay.classList.add('active');
      }, 300);
      
      // Submeter o formulário após animação
      setTimeout(() => {
        form.submit();
      }, 1000);
    });

    // Animação ao focar nos inputs
    const inputs = document.querySelectorAll('.form-input');
    inputs.forEach(input => {
      input.addEventListener('focus', () => {
        input.parentElement.style.transform = 'scale(1.02)';
        input.parentElement.style.transition = 'transform 0.3s';
      });
      
      input.addEventListener('blur', () => {
        input.parentElement.style.transform = 'scale(1)';
      });
    });

    // Adicionar efeito de hover nos departamentos (apenas desktop)
    if (window.innerWidth > 640) {
      deptItems.forEach(item => {
        item.addEventListener('mouseenter', () => {
          if (!item.classList.contains('active')) {
            item.style.background = '#f3f4f6';
          }
        });
        
        item.addEventListener('mouseleave', () => {
          if (!item.classList.contains('active')) {
            item.style.background = '#f9fafb';
          }
        });
      });
    }

    // Sistema de rotação de background
    const backgrounds = ['03.jpg', '01.jpg', '04.jpg', '05.jpg', '02.jpg'];
    const rotationInterval = 30000; // 30 segundos
    let currentBgIndex = 0;
    let currentBgElement = document.getElementById('bg1');
    let nextBgElement = document.getElementById('bg2');

    // Preload das imagens para transições suaves
    function preloadImages() {
      backgrounds.forEach((img, index) => {
        if (index !== 0) { // A primeira já está carregada
          const preloadImg = new Image();
          preloadImg.src = `../uploads/background/${img}`;
        }
      });
    }

    function changeBackground() {
      // Incrementa o índice
      currentBgIndex = (currentBgIndex + 1) % backgrounds.length;
      
      const nextImageUrl = `../uploads/background/${backgrounds[currentBgIndex]}`;
      
      // Define a próxima imagem
      nextBgElement.style.backgroundImage = `url('${nextImageUrl}')`;
      
      // Faz a transição
      currentBgElement.classList.remove('active');
      currentBgElement.classList.add('inactive');
      nextBgElement.classList.remove('inactive');
      nextBgElement.classList.add('active');
      
      // Atualiza indicadores
      updateIndicators();
      
      // Troca as referências
      const temp = currentBgElement;
      currentBgElement = nextBgElement;
      nextBgElement = temp;
    }

    // Atualiza os indicadores visuais
    function updateIndicators() {
      const indicators = document.querySelectorAll('.bg-indicator');
      indicators.forEach((indicator, index) => {
        if (index === currentBgIndex) {
          indicator.classList.add('active');
        } else {
          indicator.classList.remove('active');
        }
      });
    }

    // Preload das imagens ao carregar a página
    window.addEventListener('load', preloadImages);

    // Inicia a troca de backgrounds
    setInterval(changeBackground, rotationInterval);

    // Ajuste para prevenir scroll no iOS quando o teclado aparece
    if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
      inputs.forEach(input => {
        input.addEventListener('focus', () => {
          // Não usar position fixed para melhor UX em mobile
          setTimeout(() => {
            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }, 300);
        });
      });
    }

    // Detectar se é mobile e ajustar layout
    function adjustMobileLayout() {
      const isMobile = window.innerWidth <= 640;
      
      if (isMobile) {
        // Garantir que a seção de departamentos está oculta
        const deptShowcase = document.querySelector('.dept-showcase');
        if (deptShowcase) {
          deptShowcase.style.display = 'none';
        }
      }
    }

    // Ajustar no carregamento e redimensionamento
    window.addEventListener('load', adjustMobileLayout);
    window.addEventListener('resize', adjustMobileLayout);

    // ========== FUNCIONALIDADE ADICIONAL: LIMPAR EMAIL ==========
    // Permitir que o usuário limpe o email salvo segurando Ctrl+Shift+L
    document.addEventListener('keydown', (e) => {
      if (e.ctrlKey && e.shiftKey && e.key === 'L') {
        if (confirm('Deseja limpar o e-mail salvo?')) {
          localStorage.removeItem(STORAGE_KEY);
          emailInput.value = '';
          emailInput.focus();
          emailSaved = false;
        }
      }
    });
  </script>
</body>
</html>