#!/bin/bash
# Script d'ajuda per gestionar l'entorn de desenvolupament local Malet Torrent
# Ús: ./local-dev.sh [comando]

set -e

COMPOSE_FILE="docker-compose.local.yml"
ENV_FILE=".env.local"

# Funcions d'utilitat
print_help() {
    echo "🍰 Malet Torrent - Entorn de desenvolupament local"
    echo ""
    echo "Ús: $0 [comando]"
    echo ""
    echo "Comandes disponibles:"
    echo "  up         Aixecar tots els serveis"
    echo "  down       Aturar tots els serveis"
    echo "  restart    Reiniciar tots els serveis"
    echo "  logs       Veure logs en temps real"
    echo "  status     Veure estat dels contenidors"
    echo "  wp         Executar comandes WP-CLI"
    echo "  theme      Activar el tema malet-torrent"
    echo "  clean      Netejar volums i reiniciar"
    echo "  urls       Mostrar URLs locals"
    echo "  help       Mostrar aquesta ajuda"
    echo ""
    echo "Exemples:"
    echo "  $0 up                    # Aixecar l'entorn"
    echo "  $0 wp theme list         # Llistar temes"
    echo "  $0 wp plugin install woocommerce --activate"
    echo ""
}

print_urls() {
    echo "🌐 URLs de l'entorn local:"
    echo "  WordPress:    http://localhost:8080"
    echo "  WordPress Admin: http://localhost:8080/wp-admin"
    echo "  PHPMyAdmin:   http://localhost:8081"
    echo "  MailHog:      http://localhost:8025"
    echo "  API REST:     http://localhost:8080/wp-json/"
    echo "  Categories:   http://localhost:8080/wp-json/malet-torrent/v1/products/categories"
    echo ""
}

check_requirements() {
    if ! command -v docker-compose &> /dev/null; then
        echo "❌ docker-compose no està instal·lat"
        exit 1
    fi
    
    if [[ ! -f "$ENV_FILE" ]]; then
        echo "❌ Fitxer $ENV_FILE no trobat"
        exit 1
    fi
}

# Comandes principals
cmd_up() {
    echo "🚀 Aixecant entorn de desenvolupament local..."
    docker-compose -f $COMPOSE_FILE --env-file $ENV_FILE up -d
    echo "✅ Entorn aixecat!"
    print_urls
}

cmd_down() {
    echo "🛑 Aturant entorn..."
    docker-compose -f $COMPOSE_FILE down
    echo "✅ Entorn aturat!"
}

cmd_restart() {
    echo "🔄 Reiniciant entorn..."
    cmd_down
    sleep 2
    cmd_up
}

cmd_logs() {
    docker-compose -f $COMPOSE_FILE logs -f
}

cmd_status() {
    echo "📊 Estat dels contenidors:"
    docker-compose -f $COMPOSE_FILE ps
}

cmd_wp() {
    echo "⚡ Executant WP-CLI: $*"
    docker-compose -f $COMPOSE_FILE --env-file $ENV_FILE --profile cli run --rm wp-cli "$@"
}

cmd_theme() {
    echo "🎨 Activant tema malet-torrent..."
    cmd_wp theme activate malet-torrent
    echo "✅ Tema activat!"
}

cmd_clean() {
    echo "🧹 Netejant volums i reiniciant entorn..."
    docker-compose -f $COMPOSE_FILE down -v
    docker-compose -f $COMPOSE_FILE --env-file $ENV_FILE up -d
    echo "✅ Entorn netejat i reiniciat!"
    print_urls
}

# Router de comandes
case "${1:-help}" in
    up)
        check_requirements
        cmd_up
        ;;
    down)
        cmd_down
        ;;
    restart)
        check_requirements
        cmd_restart
        ;;
    logs)
        cmd_logs
        ;;
    status)
        cmd_status
        ;;
    wp)
        shift
        cmd_wp "$@"
        ;;
    theme)
        cmd_theme
        ;;
    clean)
        check_requirements
        cmd_clean
        ;;
    urls)
        print_urls
        ;;
    help|--help|-h)
        print_help
        ;;
    *)
        echo "❌ Comanda desconeguda: $1"
        print_help
        exit 1
        ;;
esac