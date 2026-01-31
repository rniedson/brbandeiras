// ==================== IMPORTS E SETUP ====================
const { useState, useEffect, useRef, useMemo } = React;

// Carregar Turf.js para operações de geoprocessamento
// Necessário para dissolver polígonos e criar contornos externos
if (!window.turf) {
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/@turf/turf@6/turf.min.js';
    script.async = false;
    document.head.appendChild(script);
}

// ==================== PROVEDORES DE MAPA ====================

const PROVEDORES_MAPA = {
    osm: {
        nome: 'OpenStreetMap',
        url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    },
    cartodb_dark: {
        nome: 'CartoDB Dark Matter',
        url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
        attribution: '© OpenStreetMap contributors © CARTO',
        maxZoom: 19
    },
    cartodb_light: {
        nome: 'CartoDB Positron',
        url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
        attribution: '© OpenStreetMap contributors © CARTO',
        maxZoom: 19
    },
    satellite: {
        nome: 'Satélite (Esri)',
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        attribution: 'Tiles © Esri',
        maxZoom: 18
    },
    topo: {
        nome: 'Topográfico (OpenTopoMap)',
        url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
        attribution: '© OpenStreetMap contributors, © OpenTopoMap',
        maxZoom: 17
    }
};

// ==================== FUNÇÕES AUXILIARES GLOBAIS ====================

/**
 * Calcular signo zodiacal baseado na data de nascimento
 */
const calcularSigno = (dataNascimento) => {
    if (!dataNascimento) return null;
    
    const partes = dataNascimento.split('/');
    if (partes.length !== 3) return null;
    
    const dia = parseInt(partes[0]);
    const mes = parseInt(partes[1]);
    
    const signos = [
        { nome: 'Capricórnio', emoji: '♑', inicio: [12, 22], fim: [1, 19] },
        { nome: 'Aquário', emoji: '♒', inicio: [1, 20], fim: [2, 18] },
        { nome: 'Peixes', emoji: '♓', inicio: [2, 19], fim: [3, 20] },
        { nome: 'Áries', emoji: '♈', inicio: [3, 21], fim: [4, 19] },
        { nome: 'Touro', emoji: '♉', inicio: [4, 20], fim: [5, 20] },
        { nome: 'Gêmeos', emoji: '♊', inicio: [5, 21], fim: [6, 20] },
        { nome: 'Câncer', emoji: '♋', inicio: [6, 21], fim: [7, 22] },
        { nome: 'Leão', emoji: '♌', inicio: [7, 23], fim: [8, 22] },
        { nome: 'Virgem', emoji: '♍', inicio: [8, 23], fim: [9, 22] },
        { nome: 'Libra', emoji: '♎', inicio: [9, 23], fim: [10, 22] },
        { nome: 'Escorpião', emoji: '♏', inicio: [10, 23], fim: [11, 21] },
        { nome: 'Sagitário', emoji: '♐', inicio: [11, 22], fim: [12, 21] }
    ];
    
    for (let signo of signos) {
        const [mesInicio, diaInicio] = signo.inicio;
        const [mesFim, diaFim] = signo.fim;
        
        if (mesInicio === mesFim) {
            if (mes === mesInicio && dia >= diaInicio && dia <= diaFim) {
                return signo;
            }
        } else {
            if ((mes === mesInicio && dia >= diaInicio) || (mes === mesFim && dia <= diaFim)) {
                return signo;
            }
        }
    }
    
    return null;
};

/**
 * Obter ícone de rede social baseado na URL
 */
const obterIconeRedeSocial = (url) => {
    if (!url) return null;
    
    const urlLower = url.toLowerCase();
    
    if (urlLower.includes('facebook.com')) return { icone: 'fab fa-facebook', cor: '#1877F2', nome: 'Facebook' };
    if (urlLower.includes('instagram.com')) return { icone: 'fab fa-instagram', cor: '#E4405F', nome: 'Instagram' };
    if (urlLower.includes('twitter.com') || urlLower.includes('x.com')) return { icone: 'fab fa-twitter', cor: '#1DA1F2', nome: 'Twitter/X' };
    if (urlLower.includes('tiktok.com')) return { icone: 'fab fa-tiktok', cor: '#000000', nome: 'TikTok' };
    if (urlLower.includes('youtube.com')) return { icone: 'fab fa-youtube', cor: '#FF0000', nome: 'YouTube' };
    if (urlLower.includes('linkedin.com')) return { icone: 'fab fa-linkedin', cor: '#0A66C2', nome: 'LinkedIn' };
    if (urlLower.includes('whatsapp')) return { icone: 'fab fa-whatsapp', cor: '#25D366', nome: 'WhatsApp' };
    if (urlLower.includes('kwai.com')) return { icone: 'fas fa-video', cor: '#FF6600', nome: 'Kwai' };
    if (urlLower.includes('threads.net')) return { icone: 'fab fa-threads', cor: '#000000', nome: 'Threads' };
    if (urlLower.includes('flickr.com')) return { icone: 'fab fa-flickr', cor: '#FF0084', nome: 'Flickr' };
    
    return { icone: 'fas fa-link', cor: '#64748b', nome: 'Website' };
};

/**
 * Formatar data de aniversário (DD/MM)
 */
const formatarAniversario = (dataNascimento) => {
    if (!dataNascimento) return null;
    
    const partes = dataNascimento.split('/');
    if (partes.length !== 3) return null;
    
    const dia = partes[0];
    const mes = partes[1];
    
    const meses = [
        'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
    ];
    
    const mesNome = meses[parseInt(mes) - 1];
    return `${dia} de ${mesNome}`;
};

/**
 * Normalizar nome de município
 */
const normalizarNome = (nome) => {
    if (!nome) return '';
    return nome.toUpperCase()
        .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
        .trim();
};

// Cores predefinidas para o seletor
const PRESET_COLORS = [
    '#ef4444', '#f97316', '#f59e0b', '#eab308',
    '#84cc16', '#22c55e', '#10b981', '#14b8a6',
    '#06b6d4', '#0ea5e9', '#3b82f6', '#6366f1',
    '#8b5cf6', '#a855f7', '#d946ef', '#ec4899',
    '#f43f5e', '#64748b'
];

// Paleta de cores para regiões
const CORES_REGIOES = {
    'Centro Goiano': '#3b82f6',
    'Leste Goiano': '#22c55e',
    'Noroeste Goiano': '#f59e0b',
    'Norte Goiano': '#8b5cf6',
    'Oeste Goiano': '#ec4899',
    'Sul Goiano': '#ef4444'
};

// Paleta de cores para microrregiões (sortidas)
const CORES_MICRORREGIOES = [
    '#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16', '#22c55e',
    '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9', '#3b82f6', '#6366f1',
    '#8b5cf6', '#a855f7', '#d946ef', '#ec4899', '#f43f5e', '#64748b',
    '#78716c', '#a3a3a3', '#dc2626', '#ea580c', '#ca8a04', '#65a30d',
    '#16a34a', '#0d9488', '#0891b2', '#0284c7', '#2563eb', '#4f46e5',
    '#7c3aed', '#9333ea', '#c026d3', '#db2777', '#e11d48'
];

// Cores para partidos políticos
const CORES_PARTIDOS = {
    'MDB': '#4CAF50',
    'PT': '#E53935',
    'PSDB': '#2196F3',
    'PP': '#1976D2',
    'PL': '#1565C0',
    'UNIÃO': '#9C27B0',
    'REPUBLICANOS': '#00BCD4',
    'PSD': '#FFC107',
    'PDT': '#FF9800',
    'PSB': '#FF5722',
    'PODE': '#795548',
    'CIDADANIA': '#9E9E9E',
    'SOLIDARIEDADE': '#607D8B',
    'AVANTE': '#3F51B5',
    'PROS': '#00ACC1',
    'PCdoB': '#D32F2F',
    'PSOL': '#F44336',
    'NOVO': '#FF6F00',
    'REDE': '#388E3C',
    'PMB': '#8BC34A',
    'PRTB': '#CDDC39',
    'DC': '#FDD835',
    'PMN': '#FFB300',
    'AGIR': '#FB8C00'
};

// ==================== CONFIGURAÇÃO DE GRUPOS E CAMADAS ====================
const GRUPOS_CAMADAS = [
  {
    nome: 'Rodovias Estaduais',
    collapsed: false,
    camadas: [
      {
        arquivo: 'data/rodovias_goias.json',
        nome: 'Rodovias Estaduais GO',
        cor: '#10b981',
        opacity: 0.5,
        visible: true,
        filtrosIniciais: { Unidade_Federacao: 'GO' }
      },
      {
        arquivo: 'data/cpr_goias.json',
        nome: 'PRE - Polícia Rodoviária Estadual',
        cor: '#8b5cf6',
        opacity: 0.7,
        visible: true,
        filtrosIniciais: {}
      }
    ]
  },

  {
    nome: 'Rodovias Federais',
    collapsed: false,
    camadas: [
      {
        arquivo: 'data/prf.json',
        nome: 'PRF Goiás',
        cor: '#ef4444',
        opacity: 1.0,
        visible: false,
        filtrosIniciais: { Unidade_Federacao: 'GO' }
      },
      {
        arquivo: 'data/rodovias_federais.json',
        nome: 'Rodovias Federais GO',
        cor: '#3b82f6',
        opacity: 1.0,
        visible: false,
        filtrosIniciais: { sg_uf: 'GO' }
      }
    ]
  },

  {
    nome: 'ISP',
    collapsed: true,
    camadas: [
      {
        arquivo: 'data/coc_goias.json',
        nome: 'COC - Comando de Operações do Cerrado',
        cor: '#ec4899',
        opacity: 0.7,
        visible: false,
        filtrosIniciais: {}
      },
      {
        arquivo: 'data/crpm01_goiania.json',
        nome: '1º CRPM - Goiânia',
        cor: '#f97316',
        opacity: 0.7,
        visible: false,
        filtrosIniciais: {}
      }
    ]
  },

  {
    nome: 'Divisões Administrativas',
    collapsed: false,
    camadas: [
      {
        tipo: 'divisao-regiao',
        nome: 'Por Região',
        arquivo: 'data/goias-municipios.geojson',
        hierarquiaArquivo: 'hierarquia-goias.json',
        cor: '#3b82f6',
        opacity: 0.6,
        visible: false,
        filtrosIniciais: {}
      },
      {
        tipo: 'divisao-microrregiao',
        nome: 'Por Microrregião',
        arquivo: 'data/goias-municipios.geojson',
        hierarquiaArquivo: 'hierarquia-goias.json',
        cor: '#22c55e',
        opacity: 0.6,
        visible: false,
        filtrosIniciais: {}
      },
      {
        tipo: 'divisao-crpm',
        nome: 'Por Comando Regional PM',
        arquivo: 'data/goias-municipios.geojson',
        crpmArquivo: 'data/pm-comandos.json',
        cor: '#8b5cf6',
        opacity: 0.6,
        visible: true,
        filtrosIniciais: {}
      },
      {
        tipo: 'limites-territoriais',
        arquivo: 'data/goias-municipios.geojson',
        nome: 'Municípios GO',
        cor: '#f59e0b',
        opacity: 0.3,
        visible: false,
        filtrosIniciais: {}
      }
    ]
  }
];


// ==================== COMPONENTES ====================

/**
 * Componente: SplashScreen
 * Tela inicial com logo Cerberus
 */
const SplashScreen = ({ show }) => {
    if (!show) return null;

    return (
        <div style={{
            position: 'fixed',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            background: 'linear-gradient(135deg, #1e293b 0%, #0f172a 100%)',
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            zIndex: 9999
        }}>
            <img 
                src="cerberus.png" 
                alt="Cerberus Logo" 
                style={{
                    maxWidth: '400px',
                    width: '80%',
                    height: 'auto',
                    marginBottom: '40px',
                    animation: 'fadeInScale 1s ease-out'
                }}
            />
            <div className="loading-spinner" style={{ marginTop: '20px' }}></div>
            <p style={{ 
                color: '#94a3b8', 
                fontSize: '18px', 
                marginTop: '30px',
                fontWeight: '500',
                letterSpacing: '1px'
            }}>
                Carregando Sistema de Geointeligência...
            </p>
        </div>
    );
};

/**
 * Componente: LogoMarca
 * Logo pequena no canto esquerdo
 */
const LogoMarca = () => {
    return (
        <div style={{
            position: 'fixed',
            top: '20px',
            left: '20px',
            zIndex: 1000,
            background: 'rgba(30, 41, 59, 0.95)',
            padding: '12px 16px',
            borderRadius: '12px',
            boxShadow: '0 4px 16px rgba(0,0,0,0.3)',
            backdropFilter: 'blur(10px)'
        }}>
            <img 
                src="cerberus.png" 
                alt="Cerberus" 
                style={{
                    height: '40px',
                    width: 'auto'
                }}
            />
        </div>
    );
};

/**
 * Componente: ConfigModal
 * Modal de configurações do mapa
 */
const ConfigModal = ({ show, onClose, provedorAtual, onChangeProvedor, modoEscuro, onToggleModoEscuro }) => {
    if (!show) return null;

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal" onClick={(e) => e.stopPropagation()}>
                <h2 style={{ fontSize: '24px', fontWeight: 'bold', marginBottom: '24px', color: 'white' }}>
                    <i className="fas fa-cog mr-2"></i>
                    Configurações
                </h2>

                {/* Modo Escuro */}
                <div style={{ marginBottom: '24px', padding: '16px', background: 'rgba(255,255,255,0.05)', borderRadius: '12px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '8px' }}>
                        <div>
                            <div style={{ fontSize: '16px', fontWeight: '600', color: 'white', marginBottom: '4px' }}>
                                <i className={`fas ${modoEscuro ? 'fa-moon' : 'fa-sun'} mr-2`}></i>
                                Modo Escuro
                            </div>
                            <div style={{ fontSize: '12px', color: '#94a3b8' }}>
                                Alterna entre tema claro e escuro da interface
                            </div>
                        </div>
                        <label className="toggle-switch" style={{ marginLeft: '16px' }}>
                            <input
                                type="checkbox"
                                checked={modoEscuro}
                                onChange={onToggleModoEscuro}
                            />
                            <span className="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                {/* Provedor de Mapa */}
                <div style={{ marginBottom: '24px' }}>
                    <label style={{ display: 'block', marginBottom: '12px', color: '#94a3b8', fontSize: '14px', fontWeight: '600' }}>
                        <i className="fas fa-map mr-2"></i>
                        Provedor de Mapa
                    </label>
                    <div style={{ display: 'grid', gap: '8px' }}>
                        {Object.keys(PROVEDORES_MAPA).map(key => {
                            const provedor = PROVEDORES_MAPA[key];
                            const selecionado = key === provedorAtual;
                            
                            return (
                                <div
                                    key={key}
                                    onClick={() => onChangeProvedor(key)}
                                    style={{
                                        padding: '12px 16px',
                                        background: selecionado ? 'rgba(59, 130, 246, 0.2)' : 'rgba(255,255,255,0.05)',
                                        border: selecionado ? '2px solid #3b82f6' : '2px solid rgba(255,255,255,0.1)',
                                        borderRadius: '8px',
                                        cursor: 'pointer',
                                        transition: 'all 0.2s',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between'
                                    }}
                                    onMouseEnter={(e) => {
                                        if (!selecionado) {
                                            e.currentTarget.style.background = 'rgba(255,255,255,0.08)';
                                        }
                                    }}
                                    onMouseLeave={(e) => {
                                        if (!selecionado) {
                                            e.currentTarget.style.background = 'rgba(255,255,255,0.05)';
                                        }
                                    }}
                                >
                                    <span style={{ fontSize: '14px', color: 'white', fontWeight: selecionado ? '600' : '400' }}>
                                        {provedor.nome}
                                    </span>
                                    {selecionado && (
                                        <i className="fas fa-check-circle" style={{ color: '#3b82f6', fontSize: '16px' }}></i>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>

                <div style={{ display: 'flex', gap: '12px' }}>
                    <button onClick={onClose} className="btn btn-primary" style={{ flex: 1 }}>
                        <i className="fas fa-check mr-1"></i>
                        Concluir
                    </button>
                </div>
            </div>
        </div>
    );
};

/**
 * Componente: LayerGroup
 * Representa um grupo/pasta de camadas com collapse e toggle em lote
 */
const LayerGroup = ({ grupo, onToggleCollapse, onToggleAllLayers, children }) => {
    const [collapsed, setCollapsed] = useState(grupo.collapsed);
    
    const handleToggleCollapse = () => {
        const novoEstado = !collapsed;
        setCollapsed(novoEstado);
        if (onToggleCollapse) {
            onToggleCollapse(grupo.id, novoEstado);
        }
    };
    
    const handleToggleAll = (e) => {
        e.stopPropagation();
        if (onToggleAllLayers) {
            onToggleAllLayers(grupo.id);
        }
    };
    
    const camadasAtivas = grupo.camadas.filter(c => c.visible).length;
    const totalCamadas = grupo.camadas.length;
    const todasAtivas = camadasAtivas === totalCamadas;
    
    return (
        <div className="layer-group">
            <div className="group-header" onClick={handleToggleCollapse}>
                <i className={`fas fa-chevron-down group-collapse-icon ${collapsed ? 'collapsed' : ''}`}></i>
                <i className="fas fa-folder text-yellow-400" style={{fontSize: '14px'}}></i>
                <span className="group-title">{grupo.nome}</span>
                <span className="group-badge">{camadasAtivas}/{totalCamadas}</span>
                <label className="toggle-switch" onClick={(e) => e.stopPropagation()}>
                    <input type="checkbox" checked={todasAtivas} onChange={handleToggleAll} />
                    <span className="toggle-slider"></span>
                </label>
            </div>
            <div className={`group-layers ${collapsed ? 'collapsed' : ''}`}>
                {children}
            </div>
        </div>
    );
};

/**
 * Componente: LayerCard
 * Card individual de camada com todos os controles
 */
const LayerCard = ({ 
    camada, 
    index,
    onToggle, 
    onDelete, 
    onChangeColor, 
    onChangeOpacity, 
    onUpdateFilters,
    onDragStart,
    onDragEnd,
    onDragOver,
    onDrop
}) => {
    const [showProperties, setShowProperties] = useState(false);
    const [showColorPicker, setShowColorPicker] = useState(false);
    const [localFilters, setLocalFilters] = useState(camada.filters || {});
    const [isDragging, setIsDragging] = useState(false);

    // Extrair propriedades únicas do GeoJSON
    const uniqueProps = useMemo(() => {
        if (!camada.geojson?.features) return {};
        
        const props = {};
        camada.geojson.features.forEach(feature => {
            Object.keys(feature.properties || {}).forEach(key => {
                if (!props[key]) props[key] = new Set();
                props[key].add(feature.properties[key]);
            });
        });
        
        Object.keys(props).forEach(key => {
            props[key] = Array.from(props[key]).filter(v => v != null).sort();
        });
        
        return props;
    }, [camada.geojson]);

    const applyFilters = () => {
        onUpdateFilters(camada.id, localFilters);
        setShowProperties(false);
    };

    const handleDragStart = (e) => {
        setIsDragging(true);
        onDragStart(e, index);
    };

    const handleDragEnd = (e) => {
        setIsDragging(false);
        onDragEnd(e);
    };

    const handleDragOver = (e) => {
        e.preventDefault();
        onDragOver(e, index);
    };

    const handleDrop = (e) => {
        e.preventDefault();
        onDrop(e, index);
    };

    // Se for camada de divisão administrativa, não mostrar seletor de cor
    const isDivisaoAdmin = camada.tipo === 'divisao-regiao' || camada.tipo === 'divisao-microrregiao' || camada.tipo === 'divisao-partido' || camada.tipo === 'divisao-partido-secreto' || camada.tipo === 'divisao-crpm';

    return (
        <div 
            className={`layer-card ${camada.visible ? 'active' : ''} ${isDragging ? 'dragging' : ''}`}
            draggable={true}
            onDragStart={handleDragStart}
            onDragEnd={handleDragEnd}
            onDragOver={handleDragOver}
            onDrop={handleDrop}
        >
            {/* Header do Card */}
            <div className="layer-header">
                <div className="drag-handle" title="Arrastar para reordenar">
                    <i className="fas fa-grip-vertical"></i>
                </div>
                
                {!isDivisaoAdmin && (
                    <div
                        className="layer-color-box"
                        style={{ background: camada.cor }}
                        onClick={() => setShowColorPicker(!showColorPicker)}
                        title="Mudar cor"
                    ></div>
                )}
                
                {isDivisaoAdmin && (
                    <div style={{ width: '20px', textAlign: 'center' }}>
                        <i className="fas fa-map" style={{ color: '#60a5fa', fontSize: '16px' }}></i>
                    </div>
                )}
                
                <div className="flex-1" style={{ minWidth: 0 }}>
                    <div style={{ 
                        fontSize: '12px', 
                        fontWeight: '600',
                        whiteSpace: 'nowrap',
                        overflow: 'hidden',
                        textOverflow: 'ellipsis'
                    }}>
                        {camada.nome}
                    </div>
                    <div style={{ fontSize: '9px', color: '#94a3b8' }}>
                        {camada.features} recurso(s)
                    </div>
                </div>
                
                <div className="layer-controls">
                    <button
                        className="icon-btn"
                        onClick={() => setShowProperties(!showProperties)}
                        title="Propriedades e filtros"
                        style={showProperties ? { background: '#3b82f6' } : {}}
                    >
                        <i className="fas fa-sliders-h"></i>
                    </button>
                    
                    <label className="toggle-switch">
                        <input
                            type="checkbox"
                            checked={camada.visible}
                            onChange={() => onToggle(camada.id)}
                        />
                        <span className="toggle-slider"></span>
                    </label>
                    
                    {!isDivisaoAdmin && (
                        <button
                            className="icon-btn"
                            onClick={() => onDelete(camada.id)}
                            title="Remover camada"
                        >
                            <i className="fas fa-trash"></i>
                        </button>
                    )}
                </div>
            </div>

            {/* Painel de Propriedades */}
            <div className={`properties-panel ${showProperties ? 'expanded' : ''}`}>
                {/* Seletor de Cor (apenas para camadas normais) */}
                {!isDivisaoAdmin && showColorPicker && (
                    <div className="property-section">
                        <div className="property-label">Cor da Camada</div>
                        <div className="color-picker">
                            {PRESET_COLORS.map(color => (
                                <div
                                    key={color}
                                    className={`color-option ${camada.cor === color ? 'selected' : ''}`}
                                    style={{ background: color }}
                                    onClick={() => {
                                        onChangeColor(camada.id, color);
                                        setShowColorPicker(false);
                                    }}
                                ></div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Slider de Opacidade */}
                <div className="property-section">
                    <div className="property-label">
                        Opacidade: {Math.round(camada.opacity * 100)}%
                    </div>
                    <input
                        type="range"
                        min="0"
                        max="100"
                        value={camada.opacity * 100}
                        onChange={(e) => onChangeOpacity(camada.id, e.target.value / 100)}
                        className="opacity-slider"
                    />
                </div>

                {/* Info sobre divisões administrativas */}
                {isDivisaoAdmin && (
                    <div className="property-section">
                        <div style={{ 
                            fontSize: '11px', 
                            color: '#94a3b8',
                            padding: '8px',
                            background: camada.tipo === 'divisao-partido-secreto' ? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' : 'rgba(59, 130, 246, 0.1)',
                            borderRadius: '6px',
                            lineHeight: '1.4'
                        }}>
                            <i className={`fas ${camada.tipo === 'divisao-partido-secreto' ? 'fa-gift' : 'fa-info-circle'} mr-1`} style={{ color: camada.tipo === 'divisao-partido-secreto' ? '#fff' : '#60a5fa' }}></i>
                            {camada.tipo === 'divisao-regiao' 
                                ? 'Municípios coloridos por região de Goiás'
                                : camada.tipo === 'divisao-microrregiao'
                                ? 'Municípios coloridos por microrregião de Goiás'
                                : camada.tipo === 'divisao-crpm'
                                ? 'Municípios coloridos por Comando Regional da Polícia Militar (21 CRPMs)'
                                : camada.tipo === 'divisao-partido-secreto'
                                ? '🎁 Easter Egg Desbloqueado! Dados completos dos prefeitos: profissão, idade, aniversário, signo e redes sociais!'
                                : 'Municípios coloridos por partido do prefeito eleito'
                            }
                        </div>
                    </div>
                )}

                {/* Filtros Avançados (apenas para camadas normais) */}
                {!isDivisaoAdmin && Object.keys(uniqueProps).length > 0 && (
                    <div className="property-section">
                        <div className="property-label">Filtros Avançados</div>
                        {Object.keys(uniqueProps).slice(0, 5).map(propKey => {
                            const values = uniqueProps[propKey];
                            if (values.length === 0 || values.length > 50) return null;
                            
                            return (
                                <div key={propKey} style={{ marginBottom: '8px' }}>
                                    <label style={{ 
                                        display: 'block',
                                        fontSize: '10px',
                                        color: '#94a3b8',
                                        marginBottom: '4px'
                                    }}>
                                        {propKey}
                                    </label>
                                    <select
                                        className="select-field"
                                        style={{ 
                                            padding: '6px 10px',
                                            fontSize: '12px'
                                        }}
                                        value={localFilters[propKey] || ''}
                                        onChange={(e) => {
                                            setLocalFilters(prev => ({
                                                ...prev,
                                                [propKey]: e.target.value
                                            }));
                                        }}
                                    >
                                        <option value="">Todos</option>
                                        {values.slice(0, 30).map(val => (
                                            <option key={val} value={val}>
                                                {val.toString().substring(0, 30)}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            );
                        })}
                        
                        <div style={{ display: 'flex', gap: '8px', marginTop: '12px' }}>
                            <button
                                onClick={applyFilters}
                                className="btn btn-primary"
                                style={{ 
                                    flex: 1,
                                    padding: '8px 12px',
                                    fontSize: '12px'
                                }}
                            >
                                <i className="fas fa-check mr-1"></i>
                                Aplicar
                            </button>
                            <button
                                onClick={() => {
                                    setLocalFilters({});
                                    onUpdateFilters(camada.id, {});
                                }}
                                className="btn btn-secondary"
                                style={{ 
                                    flex: 1,
                                    padding: '8px 12px',
                                    fontSize: '12px'
                                }}
                            >
                                <i className="fas fa-times mr-1"></i>
                                Limpar
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

/**
 * Componente: AddLayerModal
 * Modal para adicionar novas camadas manualmente
 */
const AddLayerModal = ({ show, onClose, onAdd }) => {
    const [nome, setNome] = useState('');
    const [cor, setCor] = useState(PRESET_COLORS[0]);
    const [arquivo, setArquivo] = useState(null);

    const handleFileChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (event) => {
                try {
                    const geojson = JSON.parse(event.target.result);
                    setArquivo({ name: file.name, data: geojson });
                } catch (error) {
                    alert('Erro ao ler arquivo GeoJSON');
                }
            };
            reader.readAsText(file);
        }
    };

    const handleSubmit = () => {
        if (!nome || !arquivo) {
            alert('Preencha todos os campos');
            return;
        }
        
        onAdd({ nome, cor, geojson: arquivo.data });
        setNome('');
        setArquivo(null);
        onClose();
    };

    if (!show) return null;

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal" onClick={(e) => e.stopPropagation()}>
                <h2 style={{ fontSize: '24px', fontWeight: 'bold', marginBottom: '24px', color: 'white' }}>
                    Adicionar Nova Camada
                </h2>

                <div style={{ marginBottom: '20px' }}>
                    <label style={{ display: 'block', marginBottom: '8px', color: '#94a3b8', fontSize: '14px' }}>
                        Nome da Camada
                    </label>
                    <input
                        type="text"
                        value={nome}
                        onChange={(e) => setNome(e.target.value)}
                        placeholder="Ex: Pontos de Interesse"
                        style={{
                            width: '100%',
                            padding: '12px',
                            borderRadius: '8px',
                            border: '2px solid rgba(255,255,255,0.1)',
                            background: 'rgba(255,255,255,0.05)',
                            color: 'white',
                            fontSize: '14px'
                        }}
                    />
                </div>

                <div style={{ marginBottom: '20px' }}>
                    <label style={{ display: 'block', marginBottom: '8px', color: '#94a3b8', fontSize: '14px' }}>
                        Cor da Camada
                    </label>
                    <div className="color-picker">
                        {PRESET_COLORS.map(color => (
                            <div
                                key={color}
                                className={`color-option ${cor === color ? 'selected' : ''}`}
                                style={{ background: color }}
                                onClick={() => setCor(color)}
                            ></div>
                        ))}
                    </div>
                </div>

                <div style={{ marginBottom: '24px' }}>
                    <label style={{ display: 'block', marginBottom: '8px', color: '#94a3b8', fontSize: '14px' }}>
                        Arquivo GeoJSON
                    </label>
                    <label className="btn btn-secondary" style={{ width: '100%', cursor: 'pointer' }}>
                        <i className="fas fa-upload"></i>
                        {arquivo ? arquivo.name : 'Selecionar Arquivo'}
                        <input
                            type="file"
                            accept=".json,.geojson"
                            onChange={handleFileChange}
                            style={{ display: 'none' }}
                        />
                    </label>
                </div>

                <div style={{ display: 'flex', gap: '12px' }}>
                    <button onClick={handleSubmit} className="btn btn-primary" style={{ flex: 1 }}>
                        <i className="fas fa-plus"></i>
                        Adicionar
                    </button>
                    <button onClick={onClose} className="btn btn-secondary" style={{ flex: 1 }}>
                        <i className="fas fa-times"></i>
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    );
};

/**
 * Componente: Loading
 * Overlay de carregamento
 */
const Loading = ({ show, message }) => {
    if (!show) return null;

    return (
        <div className="loading-overlay">
            <div className="loading-spinner"></div>
            <p style={{ marginTop: '20px', color: '#94a3b8', fontSize: '16px' }}>
                {message}
            </p>
        </div>
    );
};

/**
 * Componente: Toast
 * Notificações temporárias
 */
const Toast = ({ show, message }) => {
    if (!show) return null;

    return (
        <div className="toast">
            <i className="fas fa-check-circle mr-1" style={{ color: '#22c55e' }}></i>
            {message}
        </div>
    );
};

// ==================== COMPONENTE PRINCIPAL ====================

/**
 * App - Componente raiz do sistema
 */
const App = () => {
    // ========== ESTADO ==========
    const [grupos, setGrupos] = useState([]);
    const [camadas, setCamadas] = useState([]);
    const [loading, setLoading] = useState(true);
    const [loadingMessage, setLoadingMessage] = useState('Carregando...');
    const [toast, setToast] = useState({ show: false, message: '' });
    const [showAddModal, setShowAddModal] = useState(false);
    const [showConfigModal, setShowConfigModal] = useState(false);
    const [showSplash, setShowSplash] = useState(true);
    const [draggedIndex, setDraggedIndex] = useState(null);
    const [dragOverIndex, setDragOverIndex] = useState(null);
    const [hierarquiaGoias, setHierarquiaGoias] = useState(null);
    const [prefeitosData, setPrefeitosData] = useState(null);
    const [comandosPM, setComandosPM] = useState(null);
    const [easterEggAtivo, setEasterEggAtivo] = useState(false);
    const [sequenciaKonami, setSequenciaKonami] = useState([]);
    const [modoEscuro, setModoEscuro] = useState(false);
    const [provedorMapa, setProvedorMapa] = useState('cartodb_dark');
    
    // Refs para o mapa Leaflet
    const mapRef = useRef(null);
    const layersRef = useRef({});
    const tileLayerRef = useRef(null);

    // ========== SPLASH SCREEN ==========
    
    useEffect(() => {
        const timer = setTimeout(() => {
            setShowSplash(false);
        }, 5000); // 5 segundos

        return () => clearTimeout(timer);
    }, []);

    // ========== INICIALIZAÇÃO DO MAPA ==========
    
    useEffect(() => {
        if (showSplash) return; // Não inicializar mapa durante o splash
        
        if (!mapRef.current) {
            const map = L.map('map', {
                center: [-16.6869, -49.2648], // Goiânia, GO
                zoom: 7,
                zoomControl: true
            });

            const provedor = PROVEDORES_MAPA[provedorMapa];
            tileLayerRef.current = L.tileLayer(provedor.url, {
                attribution: provedor.attribution,
                maxZoom: provedor.maxZoom
            }).addTo(map);

            mapRef.current = map;
        }

        carregarDados();
    }, [showSplash]);

    // ========== TROCAR PROVEDOR DE MAPA ==========
    
    useEffect(() => {
        if (!mapRef.current || !tileLayerRef.current) return;
        
        // Remover tile layer antigo
        mapRef.current.removeLayer(tileLayerRef.current);
        
        // Adicionar novo tile layer
        const provedor = PROVEDORES_MAPA[provedorMapa];
        tileLayerRef.current = L.tileLayer(provedor.url, {
            attribution: provedor.attribution,
            maxZoom: provedor.maxZoom
        }).addTo(mapRef.current);
        
        mostrarToast(`Mapa alterado para: ${provedor.nome}`);
    }, [provedorMapa]);

    // ========== APLICAR MODO ESCURO ==========
    
    useEffect(() => {
        if (modoEscuro) {
            document.body.classList.add('dark-mode');
        } else {
            document.body.classList.remove('dark-mode');
        }
    }, [modoEscuro]);

    // ========== EASTER EGG - KONAMI CODE ==========
    
    useEffect(() => {
        const konamiCode = ['ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight', 'b', 'a'];
        let posicao = 0;
        
        const handleKeyDown = (e) => {
            const teclaEsperada = konamiCode[posicao];
            
            if (e.key === teclaEsperada) {
                posicao++;
                
                if (posicao === konamiCode.length) {
                    ativarEasterEgg();
                    posicao = 0;
                }
            } else {
                posicao = 0;
            }
        };
        
        window.addEventListener('keydown', handleKeyDown);
        
        return () => {
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [easterEggAtivo]);

    // ========== EASTER EGG ==========
    
    const ativarEasterEgg = async () => {
        if (easterEggAtivo) return;
        
        setEasterEggAtivo(true);
        mostrarToast('🎉 Easter Egg Desbloqueado! Camada Secreta dos Prefeitos ativada!');
        
        try {
            const response = await fetch('data/goias-municipios.geojson');
            if (response.ok) {
                const geojsonOriginal = await response.json();
                
                const camadasecreta = {
                    id: 'easter-egg-prefeitos',
                    nome: '🎁 Dados Completos dos Prefeitos',
                    cor: '#3b82f6',
                    geojson: geojsonOriginal,
                    features: geojsonOriginal.features?.length || 0,
                    visible: true,
                    opacity: 0.7,
                    tipo: 'divisao-partido-secreto',
                    filters: {},
                    grupoId: 'Divisões Administrativas'
                };
                
                setGrupos(prev => prev.map(g => {
                    if (g.id === 'Divisões Administrativas') {
                        return {
                            ...g,
                            camadas: [camadasecreta, ...g.camadas]
                        };
                    }
                    return g;
                }));
                
                setCamadas(prev => [camadasecreta, ...prev]);
            }
        } catch (error) {
            console.error('Erro ao carregar easter egg:', error);
        }
    };

    // ========== CARREGAMENTO DE DADOS ==========
    
    const carregarDados = async () => {
        try {
            const response = await fetch('hierarquia-goias.json');
            if (response.ok) {
                const data = await response.json();
                setHierarquiaGoias(data);
                console.log('✓ Hierarquia de Goiás carregada');
            }
        } catch (error) {
            console.warn('⚠ Erro ao carregar hierarquia:', error);
        }

        try {
            const response = await fetch('prefeitos.json');
            if (response.ok) {
                const data = await response.json();
                setPrefeitosData(data);
                console.log('✓ Dados de prefeitos carregados:', data.length, 'registros');
            }
        } catch (error) {
            console.warn('⚠ Erro ao carregar prefeitos:', error);
        }

        try {
            const response = await fetch('data/pm-comandos.json');
            if (response.ok) {
                const data = await response.json();
                setComandosPM(data);
                console.log('✓ Comandos Regionais PM carregados:', data.camadas.length, 'comandos');
            }
        } catch (error) {
            console.warn('⚠ Erro ao carregar comandos PM:', error);
        }
        
        carregarCamadasIniciais();
    };

    // ========== FUNÇÕES DE MAPEAMENTO ==========
    
    const criarMapaMunicipioRegiao = (hierarquia) => {
        const mapa = {};
        if (!hierarquia) return mapa;
        
        Object.keys(hierarquia).forEach(regiao => {
            Object.keys(hierarquia[regiao]).forEach(microrregiao => {
                hierarquia[regiao][microrregiao].forEach(municipio => {
                    mapa[municipio] = regiao;
                });
            });
        });
        
        return mapa;
    };

    const criarMapaMunicipioMicrorregiao = (hierarquia) => {
        const mapa = {};
        if (!hierarquia) return mapa;
        
        Object.keys(hierarquia).forEach(regiao => {
            Object.keys(hierarquia[regiao]).forEach(microrregiao => {
                hierarquia[regiao][microrregiao].forEach(municipio => {
                    mapa[municipio] = microrregiao;
                });
            });
        });
        
        return mapa;
    };

    const criarMapaMunicipioCRPM = (comandosPM) => {
        const mapa = {};
        const mapaCores = {};
        
        if (!comandosPM || !comandosPM.camadas) return { mapa, mapaCores };
        
        comandosPM.camadas.forEach(comando => {
            const nomeComando = comando.nome;
            const corComando = comando.cor;
            
            comando.municipios.forEach(municipio => {
                mapa[municipio] = nomeComando;
                mapaCores[nomeComando] = corComando;
            });
        });
        
        return { mapa, mapaCores };
    };

    const criarMapaMunicipioPartido = (prefeitos) => {
        const mapa = {};
        if (!prefeitos) return mapa;
        
        prefeitos.forEach(prefeito => {
            const cidade = prefeito.CIDADE;
            const partido = prefeito.SG_PARTIDO;
            if (cidade && partido) {
                mapa[cidade] = {
                    partido: partido,
                    prefeito: prefeito.NM_URNA_CANDIDATO || prefeito.NM_CANDIDATO,
                    idade: prefeito.IDADE,
                    ocupacao: prefeito.DS_OCUPACAO,
                    votos: prefeito.Votos,
                    reeleicao: prefeito['Releição'] === 'S'
                };
            }
        });
        
        return mapa;
    };

    // ========== CARREGAMENTO DE CAMADAS ==========
    
    const carregarCamadasIniciais = async () => {
        if (GRUPOS_CAMADAS.length === 0) {
            setLoading(false);
            return;
        }

        try {
            const gruposCarregados = [];
            const todasCamadas = [];
            
            for (const grupoConfig of GRUPOS_CAMADAS) {
                const camadasDoGrupo = [];
                
                for (const config of grupoConfig.camadas) {
                    try {
                        setLoadingMessage(`Carregando ${config.nome}...`);
                        
                        if (config.tipo === 'divisao-regiao' || config.tipo === 'divisao-microrregiao' || config.tipo === 'divisao-partido' || config.tipo === 'divisao-crpm') {
                            const response = await fetch(config.arquivo);
                            
                            if (response.ok) {
                                const geojsonOriginal = await response.json();
                                
                                const novaCamada = {
                                    id: Date.now() + Math.random(),
                                    nome: config.nome,
                                    cor: config.cor,
                                    geojson: geojsonOriginal,
                                    features: geojsonOriginal.features?.length || 0,
                                    visible: config.visible !== undefined ? config.visible : true,
                                    opacity: config.opacity !== undefined ? config.opacity : 0.6,
                                    tipo: config.tipo,
                                    filters: {},
                                    grupoId: grupoConfig.nome
                                };
                                
                                camadasDoGrupo.push(novaCamada);
                                todasCamadas.push(novaCamada);
                                console.log(`✓ Camada "${config.nome}" carregada`);
                            }
                        } else {
                            const response = await fetch(config.arquivo);
                            
                            if (response.ok) {
                                const data = await response.json();
                                
                                const novaCamada = {
                                    id: Date.now() + Math.random(),
                                    nome: config.nome,
                                    cor: config.cor,
                                    geojson: data,
                                    features: data.features?.length || 0,
                                    visible: config.visible !== undefined ? config.visible : true,
                                    opacity: config.opacity !== undefined ? config.opacity : 0.8,
                                    tipo: 'auto-loaded',
                                    filters: config.filtrosIniciais || {},
                                    grupoId: grupoConfig.nome
                                };
                                
                                camadasDoGrupo.push(novaCamada);
                                todasCamadas.push(novaCamada);
                                console.log(`✓ Camada "${config.nome}" carregada`);
                            }
                        }
                    } catch (error) {
                        console.warn(`⚠ Erro ao carregar "${config.arquivo}":`, error.message);
                    }
                }
                
                if (camadasDoGrupo.length > 0) {
                    gruposCarregados.push({
                        id: grupoConfig.nome,
                        nome: grupoConfig.nome,
                        collapsed: grupoConfig.collapsed !== undefined ? grupoConfig.collapsed : false,
                        camadas: camadasDoGrupo
                    });
                }
            }
            
            if (gruposCarregados.length > 0) {
                setGrupos(gruposCarregados);
                setCamadas(todasCamadas);
                mostrarToast(`${todasCamadas.length} camada(s) em ${gruposCarregados.length} grupo(s) carregadas!`);
            }
            
            setLoading(false);
        } catch (error) {
            console.error('Erro ao carregar grupos:', error);
            setLoading(false);
        }
    };

    // ========== OPERAÇÕES DE CAMADAS ==========
    
    const adicionarCamada = ({ nome, cor, geojson }) => {
        const novaCamada = {
            id: Date.now(),
            nome,
            cor,
            geojson,
            features: geojson.features?.length || 0,
            visible: true,
            opacity: 0.8,
            tipo: 'custom',
            filters: {},
            grupoId: 'Manual'
        };
        
        setCamadas(prev => [...prev, novaCamada]);
        
        setGrupos(prev => {
            const grupoManual = prev.find(g => g.id === 'Manual');
            if (grupoManual) {
                return prev.map(g => 
                    g.id === 'Manual' 
                        ? { ...g, camadas: [...g.camadas, novaCamada] }
                        : g
                );
            } else {
                return [...prev, {
                    id: 'Manual',
                    nome: 'Camadas Manuais',
                    collapsed: false,
                    camadas: [novaCamada]
                }];
            }
        });
        
        setShowAddModal(false);
        mostrarToast(`Camada "${nome}" adicionada!`);
    };

    const toggleCamada = (id) => {
        setCamadas(prev => prev.map(c =>
            c.id === id ? { ...c, visible: !c.visible } : c
        ));
        
        setGrupos(prev => prev.map(g => ({
            ...g,
            camadas: g.camadas.map(c =>
                c.id === id ? { ...c, visible: !c.visible } : c
            )
        })));
    };

    const removerCamada = (id) => {
        if (confirm('Deseja realmente remover esta camada?')) {
            setCamadas(prev => prev.filter(c => c.id !== id));
            setGrupos(prev => prev.map(g => ({
                ...g,
                camadas: g.camadas.filter(c => c.id !== id)
            })));
            mostrarToast('Camada removida!');
        }
    };

    const mudarCorCamada = (id, cor) => {
        setCamadas(prev => prev.map(c =>
            c.id === id ? { ...c, cor } : c
        ));
        
        setGrupos(prev => prev.map(g => ({
            ...g,
            camadas: g.camadas.map(c =>
                c.id === id ? { ...c, cor } : c
            )
        })));
    };

    const mudarOpacidadeCamada = (id, opacity) => {
        setCamadas(prev => prev.map(c =>
            c.id === id ? { ...c, opacity } : c
        ));
        
        setGrupos(prev => prev.map(g => ({
            ...g,
            camadas: g.camadas.map(c =>
                c.id === id ? { ...c, opacity } : c
            )
        })));
    };

    const atualizarFiltrosCamada = (id, filters) => {
        setCamadas(prev => prev.map(c =>
            c.id === id ? { ...c, filters } : c
        ));
        
        setGrupos(prev => prev.map(g => ({
            ...g,
            camadas: g.camadas.map(c =>
                c.id === id ? { ...c, filters } : c
            )
        })));
    };

    // ========== OPERAÇÕES DE GRUPOS ==========
    
    const toggleCollapseGrupo = (grupoId, collapsed) => {
        setGrupos(prev => prev.map(g =>
            g.id === grupoId ? { ...g, collapsed } : g
        ));
    };

    const toggleAllCamadasGrupo = (grupoId) => {
        const grupo = grupos.find(g => g.id === grupoId);
        if (!grupo) return;
        
        const todasAtivas = grupo.camadas.every(c => c.visible);
        const novoEstado = !todasAtivas;
        
        setCamadas(prev => prev.map(c =>
            c.grupoId === grupoId ? { ...c, visible: novoEstado } : c
        ));
        
        setGrupos(prev => prev.map(g =>
            g.id === grupoId ? {
                ...g,
                camadas: g.camadas.map(c => ({ ...c, visible: novoEstado }))
            } : g
        ));
        
        mostrarToast(`Grupo "${grupoId}" ${novoEstado ? 'ativado' : 'desativado'}!`);
    };

    // ========== DRAG AND DROP ==========
    
    const handleDragStart = (e, index) => {
        setDraggedIndex(index);
        e.dataTransfer.effectAllowed = 'move';
    };

    const handleDragEnd = (e) => {
        setDraggedIndex(null);
        setDragOverIndex(null);
    };

    const handleDragOver = (e, index) => {
        e.preventDefault();
        if (draggedIndex === null || draggedIndex === index) return;
        setDragOverIndex(index);
    };

    const handleDrop = (e, dropIndex) => {
        e.preventDefault();
        
        if (draggedIndex === null || draggedIndex === dropIndex) {
            setDraggedIndex(null);
            setDragOverIndex(null);
            return;
        }

        const novasCamadas = [...camadas];
        const [itemArrastado] = novasCamadas.splice(draggedIndex, 1);
        novasCamadas.splice(dropIndex, 0, itemArrastado);
        
        setCamadas(novasCamadas);
        setDraggedIndex(null);
        setDragOverIndex(null);
        
        mostrarToast('Ordem atualizada!');
    };

    // ========== RENDERIZAÇÃO NO MAPA ==========
    
    useEffect(() => {
        if (!mapRef.current) return;

        Object.values(layersRef.current).forEach(layer => {
            if (mapRef.current.hasLayer(layer)) {
                mapRef.current.removeLayer(layer);
            }
        });
        layersRef.current = {};

        // ⭐ INVERTER o array de camadas para renderização
        // Essa inversão faz com que:
        // - PRIMEIRA camada na sidebar (index 0 no array original) = renderizada POR ÚLTIMO = maior zIndex = SOBREPÕE tudo
        // - ÚLTIMA camada na sidebar = renderizada PRIMEIRO = menor zIndex = FICA POR BAIXO
        // Exemplo: [A, B, C] vira [C, B, A] na renderização
        // C renderizado primeiro (zIndex 1000), B depois (zIndex 1001), A por último (zIndex 1002)
        // Resultado visual: A sobrepõe B e C
        const camadasInvertidas = [...camadas].reverse();
        const totalCamadas = camadas.length;
        
        camadasInvertidas.forEach((camada, index) => {
            if (!camada.visible) return;

            let geojsonData = camada.geojson;

            if (camada.tipo !== 'divisao-regiao' && camada.tipo !== 'divisao-microrregiao' && camada.tipo !== 'divisao-partido') {
                if (camada.filters && Object.keys(camada.filters).length > 0) {
                    geojsonData = aplicarFiltros(camada.geojson, camada.filters);
                }
            }

            // zIndex crescente: primeira do array invertido = menor zIndex
            const zIndex = 1000 + index;

            // Camadas de divisão administrativa
            if (camada.tipo === 'divisao-regiao' || camada.tipo === 'divisao-microrregiao' || camada.tipo === 'divisao-partido' || camada.tipo === 'divisao-partido-secreto' || camada.tipo === 'divisao-crpm') {
                let mapaClassificacao = {};
                let paletaCores = {};
                let labelTipo = '';
                const isEasterEgg = camada.tipo === 'divisao-partido-secreto';

                if (camada.tipo === 'divisao-regiao') {
                    mapaClassificacao = criarMapaMunicipioRegiao(hierarquiaGoias);
                    paletaCores = CORES_REGIOES;
                    labelTipo = 'Região';
                } else if (camada.tipo === 'divisao-microrregiao') {
                    mapaClassificacao = criarMapaMunicipioMicrorregiao(hierarquiaGoias);
                    const classificacoes = [...new Set(Object.values(mapaClassificacao))];
                    classificacoes.forEach((microrregiao, idx) => {
                        paletaCores[microrregiao] = CORES_MICRORREGIOES[idx % CORES_MICRORREGIOES.length];
                    });
                    labelTipo = 'Microrregião';
                } else if (camada.tipo === 'divisao-crpm') {
                    const { mapa, mapaCores } = criarMapaMunicipioCRPM(comandosPM);
                    mapaClassificacao = mapa;
                    paletaCores = mapaCores;
                    labelTipo = 'Comando Regional PM';
                } else if (camada.tipo === 'divisao-partido' || isEasterEgg) {
                    const mapaPartidos = criarMapaMunicipioPartido(prefeitosData);
                    
                    Object.keys(mapaPartidos).forEach(cidade => {
                        mapaClassificacao[cidade] = mapaPartidos[cidade].partido;
                    });
                    
                    Object.keys(mapaPartidos).forEach(cidade => {
                        const partido = mapaPartidos[cidade].partido;
                        if (!paletaCores[partido]) {
                            paletaCores[partido] = CORES_PARTIDOS[partido] || '#64748b';
                        }
                    });
                    
                    labelTipo = 'Partido';
                    camada.dadosPartidos = mapaPartidos;
                }

                // ⭐ ESTRATÉGIA COM DISSOLUÇÃO DE POLÍGONOS (Turf.js):
                // 1. Renderizar municípios com BORDAS BRANCAS FINAS
                // 2. Dissolver municípios de cada grupo em um único polígono
                // 3. Renderizar apenas o CONTORNO EXTERNO com BORDA ESCURA GROSSA
                
                // Verificar se Turf.js está disponível
                const turfDisponivel = window.turf && window.turf.union;
                
                // Agrupar municípios por classificação
                const gruposPorClassificacao = {};
                
                geojsonData.features.forEach(feature => {
                    const nomeMunicipio = feature.properties.name || feature.properties.nome || feature.properties.NAME;
                    const nomeNormalizado = normalizarNome(nomeMunicipio);
                    
                    let classificacao = mapaClassificacao[nomeMunicipio] || mapaClassificacao[nomeNormalizado];
                    
                    if (!classificacao) {
                        for (let key of Object.keys(mapaClassificacao)) {
                            if (normalizarNome(key) === nomeNormalizado) {
                                classificacao = mapaClassificacao[key];
                                break;
                            }
                        }
                    }
                    
                    if (classificacao) {
                        if (!gruposPorClassificacao[classificacao]) {
                            gruposPorClassificacao[classificacao] = {
                                features: [],
                                municipios: [],
                                cor: paletaCores[classificacao] || '#64748b'
                            };
                        }
                        gruposPorClassificacao[classificacao].features.push(feature);
                        gruposPorClassificacao[classificacao].municipios.push(nomeMunicipio);
                    }
                });
                
                // CAMADA 1: Municípios individuais com BORDAS BRANCAS FINAS
                const layerMunicipios = L.geoJSON(geojsonData, {
                    style: (feature) => {
                        const nomeMunicipio = feature.properties.name || feature.properties.nome || feature.properties.NAME;
                        const nomeNormalizado = normalizarNome(nomeMunicipio);
                        
                        let classificacao = mapaClassificacao[nomeMunicipio] || mapaClassificacao[nomeNormalizado];
                        
                        if (!classificacao) {
                            for (let key of Object.keys(mapaClassificacao)) {
                                if (normalizarNome(key) === nomeNormalizado) {
                                    classificacao = mapaClassificacao[key];
                                    break;
                                }
                            }
                        }
                        
                        const cor = paletaCores[classificacao] || '#64748b';
                        
                        return {
                            fillColor: cor,
                            fillOpacity: camada.opacity,
                            color: '#ffffff',      // Branco
                            weight: 0.5,            // Bem fino
                            opacity: 0.3,           // Bem discreto
                            lineJoin: 'round'
                        };
                    }
                });

                layerMunicipios.addTo(mapRef.current);
                layersRef.current[`${camada.id}-municipios`] = layerMunicipios;
                
                // CAMADA 2: Contornos EXTERNOS dissolvidos (se Turf.js disponível)
                if (turfDisponivel) {
                    Object.keys(gruposPorClassificacao).forEach(classificacao => {
                        const grupo = gruposPorClassificacao[classificacao];
                        
                        try {
                            // Dissolver todos os polígonos do grupo em um único polígono
                            let poligonoUnificado = null;
                            
                            grupo.features.forEach(feature => {
                                if (!poligonoUnificado) {
                                    poligonoUnificado = feature;
                                } else {
                                    try {
                                        poligonoUnificado = turf.union(poligonoUnificado, feature);
                                    } catch (e) {
                                        console.warn('Erro ao unir polígonos:', e);
                                    }
                                }
                            });
                            
                            if (poligonoUnificado) {
                                // Renderizar apenas a borda do polígono unificado
                                const layerContornoExterno = L.geoJSON(poligonoUnificado, {
                                    style: {
                                        fillColor: 'transparent',
                                        fillOpacity: 0,          // SEM preenchimento
                                        color: '#1e293b',        // Escuro
                                        weight: 3,               // Grosso
                                        opacity: 0.95,           // Bem visível
                                        lineJoin: 'round',
                                        lineCap: 'round'
                                    },
                                    interactive: true
                                });
                                
                                // Tooltip para o agrupamento
                                layerContornoExterno.bindTooltip(
                                    `<div style="font-weight: bold; font-size: 14px; color: ${grupo.cor};">
                                        ${labelTipo}: ${classificacao}
                                    </div>
                                    <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">
                                        ${grupo.municipios.length} município${grupo.municipios.length > 1 ? 's' : ''}
                                    </div>`,
                                    { 
                                        sticky: true, 
                                        className: 'custom-tooltip',
                                        direction: 'top'
                                    }
                                );
                                
                                // Popup para o agrupamento
                                let popupHtml = `<div style="min-width: 280px;">`;
                                popupHtml += `<div style="font-size: 20px; font-weight: bold; margin-bottom: 12px; border-bottom: 2px solid ${grupo.cor}; padding-bottom: 8px;">`;
                                popupHtml += `${labelTipo}: ${classificacao}`;
                                popupHtml += `</div>`;
                                popupHtml += `<div style="font-size: 13px; color: #64748b;">`;
                                popupHtml += `<strong>${grupo.municipios.length} Municípios:</strong><br/>`;
                                popupHtml += `<div style="max-height: 200px; overflow-y: auto; margin-top: 8px; line-height: 1.6;">`;
                                popupHtml += grupo.municipios.sort().join(', ');
                                popupHtml += `</div></div></div>`;
                                layerContornoExterno.bindPopup(popupHtml, { maxWidth: 400 });
                                
                                layerContornoExterno.addTo(mapRef.current);
                                layersRef.current[`${camada.id}-contorno-${classificacao}`] = layerContornoExterno;
                            }
                        } catch (error) {
                            console.error('Erro ao criar contorno para', classificacao, ':', error);
                        }
                    });
                } else {
                    // Fallback: renderizar sem dissolução (como antes)
                    console.warn('Turf.js não disponível - contornos podem não estar corretos');
                    Object.keys(gruposPorClassificacao).forEach(classificacao => {
                        const grupo = gruposPorClassificacao[classificacao];
                        
                        const geojsonGrupo = {
                            type: 'FeatureCollection',
                            features: grupo.features
                        };
                        
                        const layerContornoExterno = L.geoJSON(geojsonGrupo, {
                            style: {
                                fillColor: 'transparent',
                                fillOpacity: 0,
                                color: '#1e293b',
                                weight: 3,
                                opacity: 0.9,
                                lineJoin: 'round',
                                lineCap: 'round'
                            },
                            interactive: true
                        });
                        
                        layerContornoExterno.bindTooltip(
                            `<div style="font-weight: bold; font-size: 14px; color: ${grupo.cor};">
                                ${labelTipo}: ${classificacao}
                            </div>
                            <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">
                                ${grupo.municipios.length} município${grupo.municipios.length > 1 ? 's' : ''}
                            </div>`,
                            { sticky: true, className: 'custom-tooltip', direction: 'top' }
                        );
                        
                        let popupHtml = `<div style="min-width: 280px;">`;
                        popupHtml += `<div style="font-size: 20px; font-weight: bold; margin-bottom: 12px; border-bottom: 2px solid ${grupo.cor}; padding-bottom: 8px;">`;
                        popupHtml += `${labelTipo}: ${classificacao}`;
                        popupHtml += `</div>`;
                        popupHtml += `<div style="font-size: 13px; color: #64748b;">`;
                        popupHtml += `<strong>${grupo.municipios.length} Municípios:</strong><br/>`;
                        popupHtml += `<div style="max-height: 200px; overflow-y: auto; margin-top: 8px; line-height: 1.6;">`;
                        popupHtml += grupo.municipios.sort().join(', ');
                        popupHtml += `</div></div></div>`;
                        layerContornoExterno.bindPopup(popupHtml, { maxWidth: 400 });
                        
                        layerContornoExterno.addTo(mapRef.current);
                        layersRef.current[`${camada.id}-contorno-${classificacao}`] = layerContornoExterno;
                    });
                }

            } else {
                // Camadas normais
                const layer = L.geoJSON(geojsonData, {
                    style: (feature) => ({
                        color: camada.cor,
                        weight: 3,
                        opacity: camada.opacity
                    }),
                    pointToLayer: (feature, latlng) => {
                        return L.circleMarker(latlng, {
                            radius: 6,
                            fillColor: camada.cor,
                            color: '#fff',
                            weight: 2,
                            opacity: 1,
                            fillOpacity: camada.opacity,
                            zIndexOffset: zIndex
                        });
                    },
                    onEachFeature: (feature, layer) => {
                        const props = feature.properties || {};
                        
                        let tooltipHtml = `<div style="max-width: 300px;">`;
                        tooltipHtml += `<div style="font-weight: bold; margin-bottom: 6px; color: ${camada.cor};">${camada.nome}</div>`;
                        
                        const propKeys = Object.keys(props);
                        if (propKeys.length > 0) {
                            tooltipHtml += `<div style="font-size: 11px; line-height: 1.4;">`;
                            propKeys.slice(0, 5).forEach(key => {
                                if (props[key] != null && props[key] !== '') {
                                    const value = props[key].toString();
                                    const displayValue = value.length > 30 ? value.substring(0, 30) + '...' : value;
                                    tooltipHtml += `<div style="margin: 2px 0;">`;
                                    tooltipHtml += `<strong style="color: #94a3b8;">${key}:</strong> `;
                                    tooltipHtml += `<span style="color: white;">${displayValue}</span>`;
                                    tooltipHtml += `</div>`;
                                }
                            });
                            tooltipHtml += `</div>`;
                        }
                        tooltipHtml += `</div>`;
                        
                        layer.bindTooltip(tooltipHtml);
                        
                        let popupHtml = `<div style="min-width: 250px;">`;
                        popupHtml += `<div style="font-size: 18px; font-weight: bold; color: ${camada.cor}; margin-bottom: 12px;">`;
                        popupHtml += camada.nome;
                        popupHtml += `</div>`;
                        popupHtml += `<div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; font-size: 13px;">`;
                        
                        propKeys.forEach(key => {
                            if (props[key]) {
                                const label = key.replace(/_/g, ' ').replace(/^./, str => str.toUpperCase());
                                popupHtml += `<strong>${label}:</strong> <span>${props[key]}</span>`;
                            }
                        });
                        
                        popupHtml += `</div></div>`;
                        layer.bindPopup(popupHtml);
                    }
                });

                layer.addTo(mapRef.current);
                
                layer.eachLayer((subLayer) => {
                    if (subLayer.setZIndex) {
                        subLayer.setZIndex(zIndex);
                    }
                });
                
                layersRef.current[camada.id] = layer;
            }

            if (Object.keys(layersRef.current).length === 1) {
                const bounds = Object.values(layersRef.current)[0].getBounds();
                if (bounds && bounds.isValid()) {
                    mapRef.current.fitBounds(bounds, { padding: [50, 50] });
                }
            }
        });
    }, [camadas, hierarquiaGoias, prefeitosData, comandosPM]);

    // ========== FUNÇÕES AUXILIARES ==========
    
    const aplicarFiltros = (geojson, filters) => {
        if (!geojson || !filters || Object.keys(filters).length === 0) return geojson;
        
        let features = geojson.features || [];
        
        Object.keys(filters).forEach(key => {
            const value = filters[key];
            if (value && value !== '') {
                features = features.filter(f => 
                    f.properties && f.properties[key] == value
                );
            }
        });
        
        return {
            type: 'FeatureCollection',
            features: features
        };
    };

    const mostrarToast = (message) => {
        setToast({ show: true, message });
        setTimeout(() => setToast({ show: false, message: '' }), 3000);
    };

    const totalRecursos = camadas.reduce((sum, c) => sum + (c.visible ? c.features : 0), 0);

    // ========== RENDER ==========
    
    return (
        <div className="flex h-screen">
            {/* Splash Screen */}
            <SplashScreen show={showSplash} />
            
            {/* Logo Marca */}
            {!showSplash && <LogoMarca />}
            
            {/* Mapa */}
            <div className="flex-1">
                <div id="map"></div>
                
                {grupos.length === 0 && !loading && !showSplash && (
                    <div style={{
                        position: 'absolute',
                        top: '50%',
                        left: '50%',
                        transform: 'translate(-50%, -50%)',
                        background: 'rgba(30, 41, 59, 0.95)',
                        padding: '48px',
                        borderRadius: '24px',
                        textAlign: 'center',
                        zIndex: 1000,
                        border: '2px dashed rgba(59, 130, 246, 0.5)',
                        maxWidth: '500px'
                    }}>
                        <i className="fas fa-layer-group text-6xl text-blue-400 mb-6"></i>
                        <h2 style={{ fontSize: '24px', fontWeight: 'bold', color: 'white', marginBottom: '12px' }}>
                            Adicione suas primeiras camadas
                        </h2>
                        <p style={{ color: '#94a3b8', marginBottom: '24px', fontSize: '14px' }}>
                            Clique no botão <strong>+</strong> no canto inferior direito
                        </p>
                    </div>
                )}
            </div>

            {/* Sidebar */}
            {!showSplash && (
                <div className="sidebar w-96 p-6">
                    <div className="mb-6">
                        <h1 className="text-3xl font-bold mb-1 flex items-center gap-3">
                            <i className="fas fa-layer-group text-blue-400"></i>
                            Cerbeurus / 2
                        </h1>
                        <h2 className="text-sm text-gray-200 mb-2">Análise de Geointeligência</h2>
                    </div>

                    <div className="grid grid-cols-2 gap-3 mb-6">
                        <div className="stat-card">
                            <div className="stat-value">{grupos.length}</div>
                            <div className="stat-label">Grupos</div>
                        </div>
                        <div className="stat-card">
                            <div className="stat-value">{totalRecursos}</div>
                            <div className="stat-label">Recursos</div>
                        </div>
                    </div>

                    <div className="filter-card">
                        <div className="filter-title">
                            <i className="fas fa-layer-group"></i>
                            Camadas Ativas
                        </div>
                        {grupos.length === 0 ? (
                            <p className="text-center text-gray-400 text-sm py-4">
                                Nenhuma camada adicionada
                            </p>
                        ) : (
                            <div className="space-y-2">
                                {grupos.map((grupo, grupoIndex) => (
                                    <LayerGroup
                                        key={grupo.id}
                                        grupo={grupo}
                                        onToggleCollapse={toggleCollapseGrupo}
                                        onToggleAllLayers={toggleAllCamadasGrupo}
                                    >
                                        {/* 
                                        ⭐ LÓGICA DE ORDEM DAS CAMADAS:
                                        - Camadas aparecem na ORDEM NATURAL na sidebar
                                        - PRIMEIRA camada na sidebar = SOBREPÕE todas (renderizada por último)
                                        - ÚLTIMA camada na sidebar = FICA POR BAIXO (renderizada primeiro)
                                        - A inversão acontece na renderização do mapa, não aqui
                                        */}
                                        {grupo.camadas.map((camada, camadaIndex) => {
                                            const globalIndex = grupos
                                                .slice(0, grupoIndex)
                                                .reduce((acc, g) => acc + g.camadas.length, 0) + camadaIndex;
                                            
                                            return (
                                                <LayerCard
                                                    key={camada.id}
                                                    camada={camada}
                                                    index={globalIndex}
                                                    onToggle={toggleCamada}
                                                    onDelete={removerCamada}
                                                    onChangeColor={mudarCorCamada}
                                                    onChangeOpacity={mudarOpacidadeCamada}
                                                    onUpdateFilters={atualizarFiltrosCamada}
                                                    onDragStart={handleDragStart}
                                                    onDragEnd={handleDragEnd}
                                                    onDragOver={handleDragOver}
                                                    onDrop={handleDrop}
                                                />
                                            );
                                        })}
                                    </LayerGroup>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Botão de Configurações */}
            {!showSplash && (
                <button
                    onClick={() => setShowConfigModal(true)}
                    style={{
                        position: 'fixed',
                        bottom: '180px',
                        right: '32px',
                        width: '56px',
                        height: '56px',
                        borderRadius: '50%',
                        background: 'linear-gradient(135deg, #475569 0%, #334155 100%)',
                        color: 'white',
                        border: 'none',
                        cursor: 'pointer',
                        boxShadow: '0 4px 16px rgba(0,0,0,0.3)',
                        transition: 'all 0.3s',
                        zIndex: 1000,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center'
                    }}
                    onMouseEnter={(e) => {
                        e.target.style.transform = 'scale(1.1)';
                        e.target.style.background = 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)';
                    }}
                    onMouseLeave={(e) => {
                        e.target.style.transform = 'scale(1)';
                        e.target.style.background = 'linear-gradient(135deg, #475569 0%, #334155 100%)';
                    }}
                    title="Configurações"
                >
                    <i className="fas fa-cog" style={{ fontSize: '24px' }}></i>
                </button>
            )}

            {/* FAB - Adicionar Camada */}
            {!showSplash && (
                <button
                    onClick={() => setShowAddModal(true)}
                    className="fab"
                    title="Adicionar Nova Camada"
                >
                    <i className="fas fa-plus"></i>
                </button>
            )}

            {/* Botão Secreto Easter Egg */}
            {!easterEggAtivo && !showSplash && (
                <div
                    onClick={() => {
                        const agora = Date.now();
                        const ultimoClique = window.lastEasterEggClick || 0;
                        
                        if (agora - ultimoClique < 500) {
                            window.easterEggClicks = (window.easterEggClicks || 0) + 1;
                            
                            if (window.easterEggClicks >= 3) {
                                ativarEasterEgg();
                                window.easterEggClicks = 0;
                            }
                        } else {
                            window.easterEggClicks = 1;
                        }
                        
                        window.lastEasterEggClick = agora;
                    }}
                    style={{
                        position: 'fixed',
                        bottom: '110px',
                        right: '32px',
                        width: '48px',
                        height: '48px',
                        borderRadius: '50%',
                        background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                        color: 'white',
                        border: 'none',
                        cursor: 'pointer',
                        boxShadow: '0 4px 16px rgba(102, 126, 234, 0.3)',
                        transition: 'all 0.3s',
                        zIndex: 1000,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        opacity: 0.3
                    }}
                    onMouseEnter={(e) => {
                        e.target.style.opacity = '1';
                        e.target.style.transform = 'scale(1.1)';
                    }}
                    onMouseLeave={(e) => {
                        e.target.style.opacity = '0.3';
                        e.target.style.transform = 'scale(1)';
                    }}
                    title="???"
                >
                    <i className="fas fa-gift" style={{ fontSize: '20px' }}></i>
                </div>
            )}

            {/* Modais e Feedback */}
            <ConfigModal
                show={showConfigModal}
                onClose={() => setShowConfigModal(false)}
                provedorAtual={provedorMapa}
                onChangeProvedor={setProvedorMapa}
                modoEscuro={modoEscuro}
                onToggleModoEscuro={() => setModoEscuro(!modoEscuro)}
            />
            <AddLayerModal
                show={showAddModal}
                onClose={() => setShowAddModal(false)}
                onAdd={adicionarCamada}
            />
            <Loading show={loading} message={loadingMessage} />
            <Toast message={toast.message} show={toast.show} />
        </div>
    );
};

// ==================== CSS KEYFRAMES ====================
const style = document.createElement('style');
style.textContent = `
@keyframes fadeInScale {
    0% {
        opacity: 0;
        transform: scale(0.8);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}

.custom-tooltip {
    background: rgba(30, 41, 59, 0.95) !important;
    border: 1px solid rgba(59, 130, 246, 0.5) !important;
    border-radius: 8px !important;
    padding: 8px 12px !important;
    box-shadow: 0 4px 16px rgba(0,0,0,0.3) !important;
}

.custom-tooltip::before {
    border-top-color: rgba(30, 41, 59, 0.95) !important;
}
`;
document.head.appendChild(style);

// ==================== RENDERIZAR APP ====================
ReactDOM.render(<App />, document.getElementById('root'));