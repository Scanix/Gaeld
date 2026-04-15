<?php

return [
    // Page titles
    'migration' => 'Migration des Données',
    'migration_description' => 'Importez vos données comptables existantes depuis une autre plateforme pour démarrer rapidement.',
    'new_import' => 'Nouvel Import',
    'import_history' => 'Historique des Imports',

    // Platform selection
    'select_platform' => 'Sélectionner la Plateforme Source',
    'select_platform_desc' => 'Choisissez la plateforme depuis laquelle vous migrez.',
    'supported_types' => 'Types de données supportés',
    'accepted_formats' => 'Formats acceptés',

    // Platform labels
    'platform_bexio' => 'Bexio',
    'platform_bexio_desc' => 'Importer depuis les exports CSV/XLS de Bexio.',
    'platform_banana' => 'Banana Comptabilité',
    'platform_banana_desc' => 'Importer depuis les exports TXT/CSV de Banana.',
    'platform_abacus' => 'Abacus',
    'platform_abacus_desc' => 'Importer depuis les exports CSV/XML d\'Abacus.',
    'platform_generic_csv' => 'CSV Générique',
    'platform_generic_csv_desc' => 'Importer depuis n\'importe quel fichier CSV avec mappage manuel des colonnes.',
    'platform_manual' => 'Saisie Manuelle',
    'platform_manual_desc' => 'Saisissez vos données manuellement avec un assistant guidé.',

    // Data types
    'data_types' => [
        'accounts' => 'Plan Comptable',
        'opening_balances' => 'Soldes d\'Ouverture',
        'journal_entries' => 'Écritures Comptables',
        'contacts' => 'Contacts',
        'invoices' => 'Factures',
        'expenses' => 'Dépenses',
        'fixed_assets' => 'Immobilisations',
        'year_end_closing' => 'Clôture Annuelle',
    ],

    // Wizard steps
    'step_platform' => 'Plateforme',
    'step_upload' => 'Téléchargement',
    'step_preview' => 'Aperçu',
    'step_import' => 'Import',

    // Upload step
    'upload_files' => 'Télécharger les Fichiers',
    'upload_files_desc' => 'Téléchargez vos fichiers d\'export pour chaque type de données à importer.',
    'select_data_type' => 'Type de Données',
    'upload_file' => 'Télécharger le Fichier',
    'file_parsed' => ':count lignes analysées avec succès.',
    'parsing_errors' => 'Erreurs d\'analyse',

    // Column mapping (generic CSV)
    'column_mapping' => 'Mappage des Colonnes',
    'column_mapping_desc' => 'Associez les colonnes de votre fichier CSV aux champs attendus.',
    'csv_column' => 'Colonne CSV',
    'maps_to' => 'Correspond à',
    'delimiter' => 'Délimiteur',

    // Preview step
    'preview_data' => 'Aperçu des Données',
    'preview_desc' => 'Vérifiez les données analysées avant l\'import.',
    'total_rows' => 'Total de lignes',
    'valid_rows' => 'Lignes valides',
    'invalid_rows' => 'Lignes invalides',
    'row_errors' => 'Erreurs par ligne',
    'row' => 'Ligne',

    // Account mapping
    'account_mapping' => 'Mappage des Comptes',
    'account_mapping_desc' => 'Vérifiez comment les comptes sources sont associés à votre plan comptable.',
    'source_account' => 'Compte Source',
    'target_account' => 'Compte Cible',
    'confidence' => 'Confiance',
    'high_confidence' => 'Élevée',
    'medium_confidence' => 'Moyenne',
    'low_confidence' => 'Faible',
    'no_match' => 'Aucune correspondance',
    'select_account' => 'Sélectionner un compte…',

    // Import step
    'execute_import' => 'Importer les Données',
    'execute_import_desc' => 'Importez les données validées dans votre organisation.',
    'importing' => 'Importation en cours…',
    'import_complete' => ':count types de données importés avec succès.',
    'import_partial' => ':success importés, :failed échoués.',
    'import_queued' => 'L\'import a été mis en file d\'attente. Vous pouvez vérifier le statut sur cette page.',
    'imported_count' => ':count importé(s)',
    'skipped_count' => ':count ignoré(s)',
    'failed_count' => ':count échoué(s)',

    // Results
    'import_results' => 'Résultats de l\'Import',
    'all_done' => 'Terminé !',
    'all_done_desc' => 'Vos données ont été importées avec succès. Vous pouvez maintenant commencer à utiliser Gäld.',
    'go_to_dashboard' => 'Aller au Tableau de Bord',
    'start_another' => 'Lancer un Autre Import',

    // Status badges
    'status_pending' => 'En attente',
    'status_validating' => 'Validation',
    'status_importing' => 'Importation',
    'status_completed' => 'Terminé',
    'status_failed' => 'Échoué',
    'status_partially_completed' => 'Partiellement Terminé',

    // Session
    'session_started' => 'Session de migration démarrée.',
    'session_deleted' => 'Session de migration supprimée.',
    'delete_session' => 'Supprimer la Session',
    'delete_session_confirm' => 'Êtes-vous sûr de vouloir supprimer cette session de migration ?',
    'no_sessions' => 'Aucun import précédent.',
    'created_on' => 'Créé le',
    'expired_upload' => 'Les données analysées pour :type ont expiré. Veuillez télécharger le fichier à nouveau.',
    'no_rows_found' => 'Aucune ligne de données trouvée dans le fichier. Vérifiez que le format et les en-têtes de colonnes sont corrects.',

    // Errors
    'no_data_uploaded' => 'Veuillez télécharger au moins un fichier avant d\'importer.',
    'platform_not_supported' => 'Cette plateforme ne supporte pas le type de données sélectionné.',

    // Onboarding
    'onboarding_import_title' => 'Importer des Données Existantes',
    'onboarding_import_desc' => 'Vous migrez depuis une autre plateforme ? Importez vos données pour commencer.',
    'onboarding_import_btn' => 'Importer les Données',
    'onboarding_start_fresh' => 'Commencer à Zéro',
    'onboarding_do_later' => 'Faire Plus Tard',

    // Rollback / Undo
    'rollback_btn' => 'Annuler l\'import',
    'rollback_confirm' => 'Êtes-vous sûr ? Cela supprimera définitivement tous les enregistrements importés lors de cette session.',
    'rollback_complete' => ':deleted enregistrements importés ont été supprimés.',
    'rollback_partial' => ':deleted enregistrements supprimés, mais certains n\'ont pas pu être annulés.',
    'rollback_not_available' => 'Cette session ne peut pas être annulée.',

    // Dashboard import banner
    'import_in_progress' => 'Un import de données est en cours. Les données du tableau de bord peuvent être incomplètes jusqu\'à la fin de l\'import.',
    'import_in_progress_link' => 'Voir le statut de l\'import',

    // Platform WIP
    'platform_wip' => '(Bientôt disponible)',

    // ── Additional translations ──────────────────────────────
    'opening_balances_entry' => 'Soldes d\'ouverture',
];
