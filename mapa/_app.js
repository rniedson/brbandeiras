// ==================== IMPORTS E SETUP ====================
const { useState, useEffect, useRef, useMemo } = React;

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
        nome: 'Divisões Administrativas',
        collapsed: false,
        camadas: [
            {
                tipo: 'divisao-partido',
                nome: 'Por Partido',
                arquivo: 'data/goias-municipios.geojson',
                prefeitosArquivo: 'prefeitos.json',
                cor: '#3b82f6',
                opacity: 0.7,
                visible: true,
                filtrosIniciais: {}
            },
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
                visible: true,
                filtrosIniciais: { 'Unidade_Federacao': 'GO' }
            },
            {
                arquivo: 'data/rodovias_federais.json',
                nome: 'Rodovias Federais GO',
                cor: '#3b82f6',
                opacity: 1.0,
                visible: true,
                filtrosIniciais: { 'sg_uf': 'GO' }
            }
        ]
    },
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
                filtrosIniciais: { 'Unidade_Federacao': 'GO' }
            }
        ]
    },
    {
        nome: 'Comandos de Policiamento',
        collapsed: true,
        camadas: [
            {
                arquivo: 'data/cpr_goias.json',
                nome: 'CPR - Comando de Policiamento Rodoviário',
                cor: '#8b5cf6',
                opacity: 0.7,
                visible: false,
                filtrosIniciais: {}
            },
            {
                arquivo: 'data/coc_goias.json',
                nome: 'COC - Comando Operações Cerrado',
                cor: '#ec4899',
                opacity: 0.7,
                visible: false,
                filtrosIniciais: {}
            },
            {
                arquivo: 'data/crpm01_goiania.json',
                nome: 'CRPM01 - 1º CPR Goiânia',
                cor: '#f97316',
                opacity: 0.7,
                visible: false,
                filtrosIniciais: {}
            }
        ]
    },
    {
        nome: 'Limites Territoriais',
        collapsed: true,
        camadas: [
            {
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
    const isDivisaoAdmin = camada.tipo === 'divisao-regiao' || camada.tipo === 'divisao-microrregiao' || camada.tipo === 'divisao-partido';

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
                            background: 'rgba(59, 130, 246, 0.1)',
                            borderRadius: '6px',
                            lineHeight: '1.4'
                        }}>
                            <i className="fas fa-info-circle mr-1" style={{ color: '#60a5fa' }}></i>
                            {camada.tipo === 'divisao-regiao' 
                                ? 'Municípios coloridos por região de Goiás'
                                : camada.tipo === 'divisao-microrregiao'
                                ? 'Municípios coloridos por microrregião de Goiás'
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
    const [draggedIndex, setDraggedIndex] = useState(null);
    const [dragOverIndex, setDragOverIndex] = useState(null);
    const [hierarquiaGoias, setHierarquiaGoias] = useState(null);
    const [prefeitosData, setPrefeitosData] = useState(null);
    
    // Refs para o mapa Leaflet
    const mapRef = useRef(null);
    const layersRef = useRef({});

    // ========== INICIALIZAÇÃO DO MAPA ==========
    
    useEffect(() => {
        if (!mapRef.current) {
            const map = L.map('map', {
                center: [-16.6869, -49.2648], // Goiânia, GO
                zoom: 7,
                zoomControl: true
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);

            mapRef.current = map;
        }

        carregarDados();
    }, []);

    // ========== CARREGAMENTO DE DADOS ==========
    
    const carregarDados = async () => {
        // Carregar hierarquia
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

        // Carregar dados de prefeitos
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
        
        carregarCamadasIniciais();
    };

    // ========== FUNÇÕES DE MAPEAMENTO ==========
    
    /**
     * Criar mapeamento de município para região
     */
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

    /**
     * Criar mapeamento de município para microrregião
     */
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

    /**
     * Criar mapeamento de município para partido do prefeito
     */
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

    /**
     * Normalizar nome de município
     */
    const normalizarNome = (nome) => {
        if (!nome) return '';
        return nome.toUpperCase()
            .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
            .trim();
    };

    // ========== CARREGAMENTO DE CAMADAS ==========
    
    /**
     * Carregar grupos e camadas configuradas
     */
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
                        
                        // Se for camada de divisão administrativa
                        if (config.tipo === 'divisao-regiao' || config.tipo === 'divisao-microrregiao' || config.tipo === 'divisao-partido') {
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
                            // Camada normal
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
                            } else {
                                console.warn(`⚠ Arquivo "${config.arquivo}" não encontrado`);
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
    
    /**
     * Adicionar nova camada manualmente
     */
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
        
        // Adicionar ao grupo "Manual" ou criar novo
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

    /**
     * Toggle visibilidade de camada
     */
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

    /**
     * Remover camada
     */
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

    /**
     * Mudar cor da camada
     */
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

    /**
     * Mudar opacidade da camada
     */
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

    /**
     * Atualizar filtros de camada
     */
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
    
    /**
     * Toggle collapse de grupo
     */
    const toggleCollapseGrupo = (grupoId, collapsed) => {
        setGrupos(prev => prev.map(g =>
            g.id === grupoId ? { ...g, collapsed } : g
        ));
    };

    /**
     * Toggle todas as camadas de um grupo
     */
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

        // Limpar camadas antigas
        Object.values(layersRef.current).forEach(layer => {
            if (mapRef.current.hasLayer(layer)) {
                mapRef.current.removeLayer(layer);
            }
        });
        layersRef.current = {};

        // Renderizar camadas visíveis
        camadas.forEach((camada, index) => {
            if (!camada.visible) return;

            let geojsonData = camada.geojson;

            // Aplicar filtros (apenas para camadas normais)
            if (camada.tipo !== 'divisao-regiao' && camada.tipo !== 'divisao-microrregiao' && camada.tipo !== 'divisao-partido') {
                if (camada.filters && Object.keys(camada.filters).length > 0) {
                    geojsonData = aplicarFiltros(camada.geojson, camada.filters);
                }
            }

            const zIndex = 1000 - index;

            // Camadas de divisão administrativa
            if (camada.tipo === 'divisao-regiao' || camada.tipo === 'divisao-microrregiao' || camada.tipo === 'divisao-partido') {
                let mapaClassificacao = {};
                let paletaCores = {};
                let labelTipo = '';

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
                } else if (camada.tipo === 'divisao-partido') {
                    const mapaPartidos = criarMapaMunicipioPartido(prefeitosData);
                    
                    // Criar mapa simplificado cidade -> partido
                    Object.keys(mapaPartidos).forEach(cidade => {
                        mapaClassificacao[cidade] = mapaPartidos[cidade].partido;
                    });
                    
                    // Usar cores de partidos
                    Object.keys(mapaPartidos).forEach(cidade => {
                        const partido = mapaPartidos[cidade].partido;
                        if (!paletaCores[partido]) {
                            paletaCores[partido] = CORES_PARTIDOS[partido] || '#64748b';
                        }
                    });
                    
                    labelTipo = 'Partido';
                    
                    // Armazenar dados completos para uso nos popups
                    camada.dadosPartidos = mapaPartidos;
                }

                const layer = L.geoJSON(geojsonData, {
                    style: (feature) => {
                        const nomeMunicipio = feature.properties.name || feature.properties.nome || feature.properties.NAME;
                        const nomeNormalizado = normalizarNome(nomeMunicipio);
                        
                        let classificacao = mapaClassificacao[nomeMunicipio] || mapaClassificacao[nomeNormalizado];
                        
                        // Tentar buscar com variações
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
                            color: '#ffffff',
                            weight: 1.5,
                            opacity: 0.8
                        };
                    },
                    onEachFeature: (feature, layer) => {
                        const props = feature.properties || {};
                        const nomeMunicipio = props.name || props.nome || props.NAME;
                        const nomeNormalizado = normalizarNome(nomeMunicipio);
                        
                        let classificacao = mapaClassificacao[nomeMunicipio] || mapaClassificacao[nomeNormalizado];
                        
                        // Tentar buscar com variações
                        if (!classificacao) {
                            for (let key of Object.keys(mapaClassificacao)) {
                                if (normalizarNome(key) === nomeNormalizado) {
                                    classificacao = mapaClassificacao[key];
                                    break;
                                }
                            }
                        }
                        
                        // Tooltip
                        let tooltipHtml = `<div style="max-width: 300px;">`;
                        tooltipHtml += `<div style="font-weight: bold; margin-bottom: 6px;">${nomeMunicipio}</div>`;
                        
                        if (camada.tipo === 'divisao-partido' && camada.dadosPartidos) {
                            const dadosPrefeito = camada.dadosPartidos[nomeMunicipio] || camada.dadosPartidos[nomeNormalizado];
                            if (dadosPrefeito) {
                                tooltipHtml += `<div style="font-size: 11px;">`;
                                tooltipHtml += `<span style="color: ${paletaCores[dadosPrefeito.partido]}; font-weight: bold;">${dadosPrefeito.partido}</span> - `;
                                tooltipHtml += `${dadosPrefeito.prefeito}`;
                                tooltipHtml += `</div>`;
                            } else {
                                tooltipHtml += `<div style="font-size: 11px; color: #94a3b8;">Dados não disponíveis</div>`;
                            }
                        } else {
                            tooltipHtml += `<div style="font-size: 11px; color: #94a3b8;">${labelTipo}: ${classificacao || 'N/D'}</div>`;
                        }
                        tooltipHtml += `</div>`;
                        
                        layer.bindTooltip(tooltipHtml);
                        
                        // Popup
                        let popupHtml = `<div style="min-width: 250px;">`;
                        popupHtml += `<div style="font-size: 18px; font-weight: bold; margin-bottom: 12px;">`;
                        popupHtml += nomeMunicipio;
                        popupHtml += `</div>`;
                        
                        if (camada.tipo === 'divisao-partido' && camada.dadosPartidos) {
                            const dadosPrefeito = camada.dadosPartidos[nomeMunicipio] || camada.dadosPartidos[nomeNormalizado];
                            if (dadosPrefeito) {
                                popupHtml += `<div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; font-size: 13px;">`;
                                popupHtml += `<strong>Prefeito:</strong> <span>${dadosPrefeito.prefeito}</span>`;
                                popupHtml += `<strong>Partido:</strong> <span style="color: ${paletaCores[dadosPrefeito.partido]}; font-weight: bold;">${dadosPrefeito.partido}</span>`;
                                popupHtml += `<strong>Idade:</strong> <span>${dadosPrefeito.idade} anos</span>`;
                                popupHtml += `<strong>Ocupação:</strong> <span>${dadosPrefeito.ocupacao}</span>`;
                                popupHtml += `<strong>Votos:</strong> <span>${dadosPrefeito.votos}</span>`;
                                popupHtml += `<strong>Reeleição:</strong> <span>${dadosPrefeito.reeleicao ? 'Sim' : 'Não'}</span>`;
                                popupHtml += `</div>`;
                            } else {
                                popupHtml += `<div style="font-size: 13px; color: #94a3b8;">Dados do prefeito não disponíveis</div>`;
                            }
                        } else {
                            popupHtml += `<div style="font-size: 14px; color: #64748b;">`;
                            popupHtml += `<strong>${labelTipo}:</strong> ${classificacao || 'N/D'}`;
                            popupHtml += `</div>`;
                        }
                        
                        popupHtml += `</div>`;
                        layer.bindPopup(popupHtml);
                    }
                });

                layer.addTo(mapRef.current);
                layersRef.current[camada.id] = layer;

            } else {
                // Camadas normais (linhas e pontos)
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
                        
                        // Tooltip
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
                        
                        // Popup
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
                
                // Controlar zIndex
                layer.eachLayer((subLayer) => {
                    if (subLayer.setZIndex) {
                        subLayer.setZIndex(zIndex);
                    }
                });
                
                layersRef.current[camada.id] = layer;
            }

            // Ajustar zoom na primeira camada
            if (Object.keys(layersRef.current).length === 1) {
                const bounds = layersRef.current[camada.id].getBounds();
                if (bounds.isValid()) {
                    mapRef.current.fitBounds(bounds, { padding: [50, 50] });
                }
            }
        });
    }, [camadas, hierarquiaGoias, prefeitosData]);

    // ========== FUNÇÕES AUXILIARES ==========
    
    /**
     * Aplicar filtros a um GeoJSON
     */
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

    /**
     * Mostrar toast
     */
    const mostrarToast = (message) => {
        setToast({ show: true, message });
        setTimeout(() => setToast({ show: false, message: '' }), 3000);
    };

    /**
     * Calcular estatísticas
     */
    const totalRecursos = camadas.reduce((sum, c) => sum + (c.visible ? c.features : 0), 0);

    // ========== RENDER ==========
    
    return (
        <div className="flex h-screen">
            {/* Mapa */}
            <div className="flex-1">
                <div id="map"></div>
                
                {grupos.length === 0 && !loading && (
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
            <div className="sidebar w-96 p-6">
                {/* Header */}
                <div className="mb-6">
                    <h1 className="text-3xl font-bold mb-1 flex items-center gap-3">
                        <i className="fas fa-layer-group text-blue-400"></i>
                        Atlas/2
                    </h1>
                    <h3 className="text-sm text-gray-400 mb-2">Análise de Geointeligência</h3>
                </div>

                {/* Estatísticas */}
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

                {/* Lista de Grupos e Camadas */}
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

            {/* FAB */}
            <button
                onClick={() => setShowAddModal(true)}
                className="fab"
                title="Adicionar Nova Camada"
            >
                <i className="fas fa-plus"></i>
            </button>

            {/* Modais e Feedback */}
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

// ==================== RENDERIZAR APP ====================
ReactDOM.render(<App />, document.getElementById('root'));
