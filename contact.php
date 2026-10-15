<?php
// La brigade propreté : envoi des demandes de devis par e-mail.
// Pour changer le destinataire, modifier uniquement la ligne DEST ci-dessous.
const DEST  = 'direction@brigadeproprete.com';
const EXPED = 'site@brigadeproprete.com';   // adresse du domaine, utilisée comme expéditeur technique

header('X-Content-Type-Options: nosniff');
mb_internal_encoding('UTF-8');

$json = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

function fin(bool $ok, string $msg, bool $json): void {
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($ok ? 200 : 400);
        echo json_encode(['ok' => $ok, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    } else {
        header('Location: contact.html?envoi=' . ($ok ? 'ok' : 'erreur'));
    }
    exit;
}

function ligne($v, int $max): string {
    $v = preg_replace('/[\r\n\t]+/', ' ', (string)$v);
    return mb_substr(trim($v), 0, $max);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: contact.html');
    exit;
}

// Champ piège invisible : un humain ne le remplit jamais. On répond "ok" au robot sans rien envoyer.
if (!empty($_POST['site'])) {
    fin(true, 'ok', $json);
}

$nom   = ligne($_POST['nom'] ?? '', 100);
$soc   = ligne($_POST['societe'] ?? '', 120);
$tel   = ligne($_POST['telephone'] ?? '', 30);
$mail  = ligne($_POST['email'] ?? '', 150);
$bes   = ligne($_POST['besoin'] ?? '', 80);
$freq  = ligne($_POST['frequence'] ?? '', 80);
$msg   = mb_substr(trim(str_replace("\r", '', (string)($_POST['message'] ?? ''))), 0, 4000);

if ($nom === '' || mb_strlen($tel) < 6 || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
    fin(false, 'Merci de renseigner votre nom, un téléphone et un e-mail valides.', $json);
}

$sujet = 'Demande de devis : ' . $nom . ($soc !== '' ? ' (' . $soc . ')' : '');
$corps = "Nouvelle demande de devis depuis le site brigadeproprete.com\n\n"
       . "Nom : $nom\n"
       . "Société : " . ($soc !== '' ? $soc : '-') . "\n"
       . "Téléphone : $tel\n"
       . "E-mail : $mail\n"
       . "Besoin : " . ($bes !== '' ? $bes : '-') . "\n"
       . "Fréquence : " . ($freq !== '' ? $freq : '-') . "\n\n"
       . "Message :\n" . ($msg !== '' ? $msg : '(aucun message)') . "\n";

$expediteur = '=?UTF-8?B?' . base64_encode('La brigade propreté (site)') . '?= <' . EXPED . '>';
$entetes = "From: $expediteur\r\n"
         . "Reply-To: $mail\r\n"
         . "MIME-Version: 1.0\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n"
         . "Content-Transfer-Encoding: 8bit\r\n";

$ok = @mail(DEST, '=?UTF-8?B?' . base64_encode($sujet) . '?=', $corps, $entetes, '-f' . EXPED);

fin($ok, $ok ? 'Merci, votre demande a bien été envoyée.' : "L'envoi a échoué.", $json);
