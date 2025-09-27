<?php
/**
 * Script de test per verificar que els emails de reviews funcionen
 * URL: https://wp2.malet.testart.cat/email-test.php
 */

// Carregar WordPress
require_once(__DIR__ . '/wp-config.php');

// Només permetre a administradors
if (!current_user_can('administrator')) {
    wp_die('No tens permisos per accedir a aquesta pàgina.');
}

// Simular una nova review per testejar
if (isset($_GET['test']) && $_GET['test'] === 'review') {
    // Obtenir primer producte disponible
    $products = wc_get_products(array('limit' => 1));

    if (empty($products)) {
        wp_die('No hi ha productes per testejar.');
    }

    $product = $products[0];

    // Crear comment de test (review)
    $comment_data = array(
        'comment_post_ID' => $product->get_id(),
        'comment_author' => 'Test Review',
        'comment_author_email' => 'test@example.com',
        'comment_content' => 'Aquesta és una review de test per verificar que els emails funcionen correctament.',
        'comment_type' => 'review',
        'comment_approved' => 1,
        'comment_meta' => array(
            'rating' => 5
        )
    );

    $comment_id = wp_insert_comment($comment_data);

    if ($comment_id) {
        // Afegir rating
        add_comment_meta($comment_id, 'rating', 5);

        // Triggerejar la notificació manualmente per test
        malet_notify_admin_new_review($comment_id, 1);

        echo '<div style="background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px;">';
        echo '<h3>✅ Test completat</h3>';
        echo '<p>S\'ha creat una review de test i s\'hauria d\'haver enviat un email als administradors.</p>';
        echo '<p><strong>Producte:</strong> ' . $product->get_name() . '</p>';
        echo '<p><strong>Review ID:</strong> ' . $comment_id . '</p>';
        echo '<p><a href="' . admin_url('edit-comments.php?comment_type=review') . '">Veure reviews</a></p>';
        echo '</div>';
    } else {
        echo '<div style="background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px;">';
        echo '<h3>❌ Error</h3>';
        echo '<p>No s\'ha pogut crear la review de test.</p>';
        echo '</div>';
    }

    exit;
}

// Test de protecció XSS
if (isset($_GET['test']) && $_GET['test'] === 'malicious') {
    // Obtenir primer producte disponible
    $products = wc_get_products(array('limit' => 1));

    if (empty($products)) {
        wp_die('No hi ha productes per testejar.');
    }

    $product = $products[0];

    // Crear comment de test amb contingut maliciós
    $malicious_content = '<script>alert("XSS Attack!")</script>Aquesta és una review amb codi maliciós <img src="x" onerror="alert(\'XSS\')">';
    $malicious_author = 'Hacker<script>alert("XSS")</script>';

    $comment_data = array(
        'comment_post_ID' => $product->get_id(),
        'comment_author' => $malicious_author,
        'comment_author_email' => 'hacker@malicious.com',
        'comment_content' => $malicious_content,
        'comment_type' => 'review',
        'comment_approved' => 1,
        'comment_meta' => array(
            'rating' => 1
        )
    );

    $comment_id = wp_insert_comment($comment_data);

    if ($comment_id) {
        // Afegir rating
        add_comment_meta($comment_id, 'rating', 1);

        // Obtenir comment després del processament de seguretat
        $processed_comment = get_comment($comment_id);

        echo '<div style="background: #fff3cd; color: #856404; padding: 15px; border: 1px solid #ffeeba; border-radius: 5px; margin: 20px;">';
        echo '<h3>🛡️ Test de Seguretat Completat</h3>';
        echo '<p><strong>Contingut original (maliciós):</strong><br>';
        echo '<code>' . esc_html($malicious_content) . '</code></p>';
        echo '<p><strong>Autor original (maliciós):</strong><br>';
        echo '<code>' . esc_html($malicious_author) . '</code></p>';

        echo '<h4>✅ Resultats després del filtrat:</h4>';
        echo '<p><strong>Contingut sanititzat:</strong><br>';
        echo '<code>' . esc_html($processed_comment->comment_content) . '</code></p>';
        echo '<p><strong>Autor sanititzat:</strong><br>';
        echo '<code>' . esc_html($processed_comment->comment_author) . '</code></p>';
        echo '<p><strong>Estat del comment:</strong> ' . $processed_comment->comment_approved . '</p>';

        if ($processed_comment->comment_approved === 'spam') {
            echo '<p style="color: #28a745;"><strong>🎯 ÈXIT!</strong> La review maliciosa ha estat marcada com SPAM automàticament.</p>';
        } else {
            echo '<p style="color: #dc3545;"><strong>⚠️ ATENCIÓ!</strong> La review no ha estat marcada com spam. Revisar seguretat.</p>';
        }

        echo '<p><a href="' . admin_url('edit-comments.php?comment_status=spam') . '">Veure comments SPAM</a></p>';
        echo '</div>';
    } else {
        echo '<div style="background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px;">';
        echo '<h3>❌ Error</h3>';
        echo '<p>No s\'ha pogut crear la review de test.</p>';
        echo '</div>';
    }

    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Email Reviews - Malet Torrent</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .test-button {
            background: #0073aa;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin: 10px 0;
        }
        .info {
            background: #e1f5fe;
            padding: 15px;
            border-left: 4px solid #0073aa;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <h1>🧪 Test Sistema d'Emails per Reviews</h1>

    <div class="info">
        <h3>Informació del sistema</h3>
        <ul>
            <li><strong>WordPress:</strong> <?php echo get_bloginfo('version'); ?></li>
            <li><strong>WooCommerce:</strong> <?php echo defined('WC_VERSION') ? WC_VERSION : 'No instal·lat'; ?></li>
            <li><strong>Email admin:</strong> <?php echo get_bloginfo('admin_email'); ?></li>
            <li><strong>Usuaris admin:</strong>
                <?php
                $admins = get_users(array('role' => 'administrator'));
                foreach($admins as $admin) {
                    echo $admin->user_email . ' ';
                }
                ?>
            </li>
        </ul>
    </div>

    <h3>🚀 Executar Test</h3>
    <p>Aquest test crearà una review falsa per verificar que el sistema d'emails funciona:</p>

    <a href="?test=review" class="test-button">
        🧪 Crear Review Normal i Enviar Email
    </a>

    <a href="?test=malicious" class="test-button" style="background: #dc3545;">
        🛡️ Test Protecció XSS (Review Maliciosa)
    </a>

    <div class="info">
        <h4>⚠️ Què farà aquest test:</h4>
        <ol>
            <li>Obtindrà el primer producte disponible</li>
            <li>Crearà una review de test amb 5 estrelles</li>
            <li>Enviarà un email a tots els administradors</li>
            <li>Et mostrarà el resultat</li>
        </ol>
    </div>
</body>
</html>