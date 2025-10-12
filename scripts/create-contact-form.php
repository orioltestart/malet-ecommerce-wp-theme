<?php
/**
 * Script per crear formulari de contacte personalitzat
 * Camps: Nom complert, Email, Telèfon, Assumpte, Missatge
 *
 * Executa amb: wp eval-file scripts/create-contact-form.php --allow-root
 */

// Verificar que Contact Form 7 està actiu
if (!class_exists('WPCF7_ContactForm')) {
    die("Error: Contact Form 7 no està instal·lat o activat.\n");
}

// Definir el contingut del formulari
$form_content = <<<FORM
<label> Nom complet *
    [text* full-name autocomplete:name] </label>

<label> Correu electrònic *
    [email* email autocomplete:email] </label>

<label> Telèfon *
    [tel* phone autocomplete:tel] </label>

<label> Assumpte *
    [text* subject] </label>

<label> Missatge *
    [textarea* message] </label>

[submit "Enviar"]
FORM;

// Configuració de l'email (Mail)
$mail_config = array(
    'subject' => 'Nou missatge de contacte: [subject]',
    'sender' => '[email]',
    'body' => 'De: [full-name]
Email: [email]
Telèfon: [phone]
Assumpte: [subject]

Missatge:
[message]

--
Aquest email s\'ha enviat des del formulari de contacte de Malet Torrent',
    'recipient' => get_option('admin_email'),
    'additional_headers' => 'Reply-To: [email]',
);

// Configuració de l'email de confirmació al client (Mail 2)
$mail2_config = array(
    'active' => true,
    'subject' => 'Gràcies pel teu missatge - Malet Torrent',
    'sender' => '[_site_admin_email]',
    'body' => 'Hola [full-name],

Hem rebut el teu missatge i et respondrem el més aviat possible.

Detalls del teu missatge:
Assumpte: [subject]
Missatge: [message]

Gràcies per contactar amb Malet Torrent!

--
Malet Torrent - Pastisseria Tradicional Catalana
Web: https://malet.cat',
    'recipient' => '[email]',
    'additional_headers' => '',
);

// Missatges personalitzats en català
$messages = array(
    'mail_sent_ok' => 'Gràcies pel teu missatge. S\'ha enviat correctament.',
    'mail_sent_ng' => 'Hi ha hagut un error en enviar el missatge. Si us plau, torna-ho a intentar més tard.',
    'validation_error' => 'Un o més camps tenen un error. Si us plau, verifica\'ls i torna-ho a intentar.',
    'spam' => 'Hi ha hagut un error en enviar el missatge. Si us plau, torna-ho a intentar més tard.',
    'accept_terms' => 'Has d\'acceptar els termes i condicions abans de continuar.',
    'invalid_required' => 'Aquest camp és obligatori.',
    'invalid_too_long' => 'Aquest camp és massa llarg.',
    'invalid_too_short' => 'Aquest camp és massa curt.',
    'invalid_date' => 'El format de la data no és vàlid.',
    'date_too_early' => 'La data és anterior a la mínima permesa.',
    'date_too_late' => 'La data és posterior a la màxima permesa.',
    'upload_failed' => 'Hi ha hagut un error en pujar el fitxer.',
    'upload_file_type_invalid' => 'Aquest tipus de fitxer no està permès.',
    'upload_file_too_large' => 'El fitxer és massa gran.',
    'upload_failed_php_error' => 'Hi ha hagut un error en pujar el fitxer.',
    'invalid_number' => 'El format del número no és vàlid.',
    'number_too_small' => 'El número és menor que el mínim permès.',
    'number_too_large' => 'El número és major que el màxim permès.',
    'quiz_answer_not_correct' => 'La resposta no és correcta.',
    'invalid_email' => 'L\'adreça de correu electrònic no és vàlida.',
    'invalid_url' => 'L\'URL no és vàlida.',
    'invalid_tel' => 'El número de telèfon no és vàlid.',
);

// Comprovar si ja existeix un formulari amb aquest títol
$existing_forms = get_posts(array(
    'post_type' => 'wpcf7_contact_form',
    'title' => 'API Contacte - Malet Torrent',
    'post_status' => 'any',
    'numberposts' => 1,
));

if (!empty($existing_forms)) {
    $form_id = $existing_forms[0]->ID;
    echo "ℹ️  Formulari existent trobat amb ID: {$form_id}\n";
    echo "🔄 Actualitzant formulari...\n";

    $contact_form = WPCF7_ContactForm::get_instance($form_id);
} else {
    echo "✨ Creant nou formulari...\n";
    $contact_form = WPCF7_ContactForm::get_template();
}

// Configurar les propietats del formulari
$properties = $contact_form->get_properties();

$properties['form'] = $form_content;
$properties['mail'] = array_merge($properties['mail'], $mail_config);
$properties['mail_2'] = array_merge($properties['mail_2'], $mail2_config);
$properties['messages'] = array_merge($properties['messages'], $messages);

$contact_form->set_properties($properties);
$contact_form->set_title('API Contacte - Malet Torrent');
$contact_form->set_locale('ca');

// Guardar el formulari
$form_id = $contact_form->save();

if ($form_id) {
    echo "✅ Formulari creat/actualitzat correctament!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📋 ID del formulari: {$form_id}\n";
    echo "📧 Email destinatari: " . get_option('admin_email') . "\n";
    echo "\n";
    echo "🔗 Ús des de l'API:\n";
    echo "POST /wp-json/malet-torrent/v1/forms/submit\n";
    echo "\n";
    echo "📝 Camps del formulari:\n";
    echo "  - full-name (text, obligatori)\n";
    echo "  - email (email, obligatori)\n";
    echo "  - phone (tel, obligatori)\n";
    echo "  - subject (text, obligatori)\n";
    echo "  - message (textarea, obligatori)\n";
    echo "\n";
    echo "💡 Exemple JSON:\n";
    echo json_encode(array(
        'form_id' => $form_id,
        'full-name' => 'Joan Garcia',
        'email' => 'joan@example.com',
        'phone' => '+34 666 777 888',
        'subject' => 'Consulta sobre melindros',
        'message' => 'Hola, voldria més informació...'
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
} else {
    echo "❌ Error: No s'ha pogut guardar el formulari.\n";
    exit(1);
}
