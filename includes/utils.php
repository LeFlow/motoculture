<?php
// Helpers utilitaires pour DSI Location

/**
 * Formatte une date au format français (d/m/Y)
 */
function dsi_format_date_fr($date) {
    if (!$date) return '';
    $dt = date_create($date);
    if (!$dt) $dt = DateTime::createFromFormat('m-d-y', $date);
    return $dt ? $dt->format('d/m/Y') : $date;
}

/**
 * Vérifie la présence de champs obligatoires dans un tableau (ex: $_POST)
 * @param array $fields Liste des champs attendus
 * @param array $data Tableau à vérifier (ex: $_POST)
 * @return array Liste des champs manquants (vide si tout est ok)
 */
function dsi_check_required_fields(array $fields, array $data) {
    $missing = [];
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            $missing[] = $field;
        }
    }
    return $missing;
} 

function log_error($data) {
    $log_file = DSI_PLUGIN_DIR . 'error_log.log';
    $timestamp = date("Y-m-d H:i:s");
    $message = "[$timestamp] $data\n";
    error_log($message, 3, $log_file);
}
