<?php
/**
 * Required Plugins Configuration for Malet Torrent Theme
 * Defines all plugins that should be installed automatically
 * 
 * @package Malet Torrent
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

return [
    // REQUERITS (Essencials) - Crítics per al funcionament
    'woocommerce' => [
        'name' => 'WooCommerce',
        'priority' => 'required',
        'description' => 'Plataforma d\'e-commerce per gestionar productes i vendes de melindros',
        'source' => null, // WordPress.org
        'auto_activate' => true,
        'required_for' => 'E-commerce functionality',
        'features' => [
            'Gestió de productes',
            'Carret de compra',
            'Sistema de pagaments',
            'Gestió de comandes'
        ]
    ],
    
    'contact-form-7' => [
        'name' => 'Contact Form 7',
        'priority' => 'required',
        'description' => 'Formularis de contacte flexibles i fàcils de personalitzar',
        'source' => null, // WordPress.org
        'auto_activate' => true,
        'required_for' => 'Contact functionality',
        'features' => [
            'Formularis personalitzables',
            'Protecció anti-spam',
            'Enviament per email',
            'Integració amb reCAPTCHA'
        ]
    ],
    
    // MOLT RECOMANATS (Seguretat i Backup)
    'wordfence' => [
        'name' => 'Wordfence Security',
        'priority' => 'highly_recommended',
        'description' => 'Seguretat completa amb firewall, escàner de malware i protecció d\'atacs',
        'source' => null, // WordPress.org
        'auto_activate' => true,
        'required_for' => 'Website security',
        'features' => [
            'Firewall d\'aplicacions web',
            'Escàner de malware',
            'Protecció força bruta',
            'Monitoratge en temps real'
        ]
    ],
    
    'updraftplus' => [
        'name' => 'UpdraftPlus WordPress Backup Plugin',
        'priority' => 'highly_recommended',
        'description' => 'Backup automàtic i restauració del lloc web',
        'source' => null, // WordPress.org
        'auto_activate' => true,
        'required_for' => 'Data backup and security',
        'features' => [
            'Backup automàtic',
            'Emmagatzematge al núvol',
            'Restauració fàcil',
            'Migració del lloc'
        ]
    ],
    
    'limit-login-attempts-reloaded' => [
        'name' => 'Limit Login Attempts Reloaded',
        'priority' => 'highly_recommended',
        'description' => 'Protecció contra atacs de força bruta limitant intents de login',
        'source' => null, // WordPress.org
        'auto_activate' => true,
        'required_for' => 'Login security',
        'features' => [
            'Limitació d\'intents de login',
            'Bloqueig d\'IPs malicioses',
            'Logs de seguretat',
            'Configuració flexible'
        ]
    ],
    
    // RECOMANATS (Rendiment)
    'redis-cache' => [
        'name' => 'Redis Object Cache',
        'priority' => 'recommended',
        'description' => 'Cache d\'objectes amb Redis per millor rendiment',
        'source' => null, // WordPress.org
        'auto_activate' => false, // Requereix configuració Redis
        'required_for' => 'Performance optimization',
        'features' => [
            'Cache d\'objectes ràpid',
            'Reducció de consultes DB',
            'Millor rendiment',
            'Escalabilitat'
        ]
    ],
    
    'autoptimize' => [
        'name' => 'Autoptimize',
        'priority' => 'recommended',
        'description' => 'Optimització de CSS, JavaScript i HTML per millorar la velocitat',
        'source' => null, // WordPress.org
        'auto_activate' => true,
        'required_for' => 'Performance optimization',
        'features' => [
            'Minificació de CSS/JS',
            'Combinació de fitxers',
            'Optimització d\'imatges',
            'Cache de navegador'
        ]
    ],
    
    'wp-super-cache' => [
        'name' => 'WP Super Cache',
        'priority' => 'recommended',
        'description' => 'Sistema de cache de pàgines per accelerar el lloc web',
        'source' => null, // WordPress.org
        'auto_activate' => true,
        'required_for' => 'Page caching',
        'features' => [
            'Cache de pàgines estàtiques',
            'Reducció de càrrega servidor',
            'Millor temps de càrrega',
            'CDN integration'
        ]
    ],
    
    // OPCIONALS (SEO i Utilitats)
    'seo-by-rank-math' => [
        'name' => 'Rank Math SEO',
        'priority' => 'optional',
        'description' => 'Optimització SEO completa amb moltes funcions gratuïtes',
        'source' => null, // WordPress.org
        'auto_activate' => false,
        'required_for' => 'SEO optimization',
        'features' => [
            'Optimització SEO completa',
            'Schema markup',
            'Anàlisi de contingut',
            'Integració xarxes socials'
        ]
    ],
    
    'wp-mail-smtp' => [
        'name' => 'WP Mail SMTP by WPForms',
        'priority' => 'optional',
        'description' => 'Millora l\'enviament d\'emails via SMTP',
        'source' => null, // WordPress.org
        'auto_activate' => false,
        'required_for' => 'Email delivery',
        'features' => [
            'Enviament SMTP fiable',
            'Integració amb Gmail/Outlook',
            'Logs d\'emails',
            'Test d\'enviament'
        ]
    ],
    
    'classic-editor' => [
        'name' => 'Classic Editor',
        'priority' => 'optional',
        'description' => 'Manté l\'editor clàssic de WordPress per usuaris que el prefereixin',
        'source' => null, // WordPress.org
        'auto_activate' => false,
        'required_for' => 'Content editing preference',
        'features' => [
            'Editor clàssic TinyMCE',
            'Interfície familiar',
            'Compatibilitat plugins',
            'Transició gradual'
        ]
    ],
    
    // EXPERIMENTALS (Tecnologies noves)
    'wordpress-mcp' => [
        'name' => 'WordPress MCP Server',
        'priority' => 'experimental',
        'description' => 'Integració del Model Context Protocol per IA i automatització',
        'source' => 'https://github.com/Automattic/wordpress-mcp/archive/refs/heads/main.zip',
        'auto_activate' => false,
        'required_for' => 'AI integration and automation',
        'features' => [
            'Integració amb IA',
            'Model Context Protocol',
            'APIs per automatització',
            'Funcions experimentals'
        ]
    ],
    
    // COMPLEMENTARIS WOOCOMMERCE
    'woocommerce-pdf-invoices-packing-slips' => [
        'name' => 'PDF Invoices & Packing Slips for WooCommerce',
        'priority' => 'optional',
        'description' => 'Genera factures i albarans en PDF per WooCommerce',
        'source' => null, // WordPress.org
        'auto_activate' => false,
        'required_for' => 'Invoice generation',
        'features' => [
            'Factures en PDF',
            'Albarans personalitzables',
            'Enviament automàtic',
            'Templates personalitzats'
        ]
    ],
    
    'woocommerce-gateway-stripe' => [
        'name' => 'WooCommerce Stripe Gateway',
        'priority' => 'optional',
        'description' => 'Passarel·la de pagament Stripe per WooCommerce',
        'source' => null, // WordPress.org
        'auto_activate' => false,
        'required_for' => 'Credit card payments',
        'features' => [
            'Pagaments amb targeta',
            'Pagaments recurrents',
            'Apple Pay / Google Pay',
            'Gestió de reemborsaments'
        ]
    ],
    
    // ADDONS CONTACT FORM 7
    'contact-form-cfdb7' => [
        'name' => 'Contact Form DB',
        'priority' => 'optional',
        'description' => 'Guarda els missatges de Contact Form 7 a la base de dades',
        'source' => null, // WordPress.org
        'auto_activate' => false,
        'required_for' => 'Form data storage',
        'features' => [
            'Emmagatzematge de missatges',
            'Exportació de dades',
            'Gestió des de l\'admin',
            'Cerca i filtres'
        ]
    ],
    
    // UTILITATS ADICIONALS
    'duplicate-post' => [
        'name' => 'Duplicate Post',
        'priority' => 'optional',
        'description' => 'Duplica entrades i pàgines fàcilment',
        'source' => null, // WordPress.org
        'auto_activate' => false,
        'required_for' => 'Content management',
        'features' => [
            'Duplicació d\'entrades',
            'Còpia de pàgines',
            'Estalvi de temps',
            'Plantilles de contingut'
        ]
    ],
    
    'wp-user-avatar' => [
        'name' => 'ProfilePress',
        'priority' => 'optional',
        'description' => 'Gestió avançada d\'usuaris i perfils personalitzats',
        'source' => null, // WordPress.org
        'auto_activate' => false,
        'required_for' => 'User management',
        'features' => [
            'Perfils d\'usuari',
            'Registre personalitzat',
            'Gestió d\'avatars',
            'Formularis de login'
        ]
    ]
];