<?php

return [
    // Page titles
    'migration' => 'Migrazione Dati',
    'migration_description' => 'Importa i tuoi dati contabili esistenti da un\'altra piattaforma per iniziare rapidamente.',
    'new_import' => 'Nuovo Import',
    'import_history' => 'Storico Importazioni',

    // Platform selection
    'select_platform' => 'Seleziona Piattaforma di Origine',
    'select_platform_desc' => 'Scegli la piattaforma da cui stai migrando.',
    'supported_types' => 'Tipi di dati supportati',
    'accepted_formats' => 'Formati accettati',

    // Platform labels
    'platform_bexio' => 'Bexio',
    'platform_bexio_desc' => 'Importa da esportazioni CSV/XLS di Bexio.',
    'platform_banana' => 'Banana Contabilità',
    'platform_banana_desc' => 'Importa da esportazioni TXT/CSV di Banana.',
    'platform_abacus' => 'Abacus',
    'platform_abacus_desc' => 'Importa da esportazioni CSV/XML di Abacus.',
    'platform_generic_csv' => 'CSV Generico',
    'platform_generic_csv_desc' => 'Importa da qualsiasi file CSV con mappatura manuale delle colonne.',
    'platform_manual' => 'Inserimento Manuale',
    'platform_manual_desc' => 'Inserisci i tuoi dati manualmente con una procedura guidata.',

    // Data types
    'data_types' => [
        'accounts' => 'Piano dei Conti',
        'opening_balances' => 'Saldi di Apertura',
        'journal_entries' => 'Registrazioni Contabili',
        'contacts' => 'Contatti',
        'invoices' => 'Fatture',
        'expenses' => 'Spese',
        'fixed_assets' => 'Immobilizzazioni',
        'year_end_closing' => 'Chiusura Annuale',
    ],

    // Wizard steps
    'step_platform' => 'Piattaforma',
    'step_upload' => 'Caricamento',
    'step_preview' => 'Anteprima',
    'step_import' => 'Importazione',

    // Upload step
    'upload_files' => 'Carica File',
    'upload_files_desc' => 'Carica i file di esportazione per ogni tipo di dati che vuoi importare.',
    'select_data_type' => 'Tipo di Dati',
    'upload_file' => 'Carica File',
    'file_parsed' => ':count righe analizzate con successo.',
    'parsing_errors' => 'Errori di analisi',

    // Column mapping (generic CSV)
    'column_mapping' => 'Mappatura Colonne',
    'column_mapping_desc' => 'Associa le colonne del tuo file CSV ai campi previsti.',
    'csv_column' => 'Colonna CSV',
    'maps_to' => 'Corrisponde a',
    'delimiter' => 'Delimitatore',

    // Preview step
    'preview_data' => 'Anteprima Dati',
    'preview_desc' => 'Verifica i dati analizzati prima dell\'importazione.',
    'total_rows' => 'Righe totali',
    'valid_rows' => 'Righe valide',
    'invalid_rows' => 'Righe non valide',
    'row_errors' => 'Errori per riga',
    'row' => 'Riga',

    // Account mapping
    'account_mapping' => 'Mappatura Conti',
    'account_mapping_desc' => 'Verifica come i conti di origine sono associati al tuo piano dei conti.',
    'source_account' => 'Conto di Origine',
    'target_account' => 'Conto di Destinazione',
    'confidence' => 'Affidabilità',
    'high_confidence' => 'Alta',
    'medium_confidence' => 'Media',
    'low_confidence' => 'Bassa',
    'no_match' => 'Nessuna corrispondenza',
    'select_account' => 'Seleziona conto…',

    // Import step
    'execute_import' => 'Importa Dati',
    'execute_import_desc' => 'Importa i dati convalidati nella tua organizzazione.',
    'importing' => 'Importazione in corso…',
    'import_complete' => ':count tipi di dati importati con successo.',
    'import_partial' => ':success importati, :failed falliti.',
    'import_queued' => 'L\'importazione è stata messa in coda. Puoi verificare lo stato su questa pagina.',
    'imported_count' => ':count importato/i',
    'skipped_count' => ':count ignorato/i',
    'failed_count' => ':count fallito/i',

    // Results
    'import_results' => 'Risultati dell\'Importazione',
    'all_done' => 'Fatto!',
    'all_done_desc' => 'I tuoi dati sono stati importati con successo. Ora puoi iniziare a usare Gäld.',
    'go_to_dashboard' => 'Vai alla Dashboard',
    'start_another' => 'Avvia un Altro Import',

    // Status badges
    'status_pending' => 'In attesa',
    'status_validating' => 'Convalida',
    'status_importing' => 'Importazione',
    'status_completed' => 'Completato',
    'status_failed' => 'Fallito',
    'status_partially_completed' => 'Parzialmente Completato',

    // Session
    'session_started' => 'Sessione di migrazione avviata.',
    'session_deleted' => 'Sessione di migrazione eliminata.',
    'delete_session' => 'Elimina Sessione',
    'delete_session_confirm' => 'Sei sicuro di voler eliminare questa sessione di migrazione?',
    'no_sessions' => 'Nessuna importazione precedente.',
    'created_on' => 'Creato il',
    'expired_upload' => 'I dati analizzati per :type sono scaduti. Ricarica il file.',
    'no_rows_found' => 'Nessuna riga di dati trovata nel file. Verifica che il formato e le intestazioni delle colonne siano corretti.',

    // Errors
    'no_data_uploaded' => 'Carica almeno un file prima di importare.',
    'platform_not_supported' => 'Questa piattaforma non supporta il tipo di dati selezionato.',

    // Onboarding
    'onboarding_import_title' => 'Importa Dati Esistenti',
    'onboarding_import_desc' => 'Migri da un\'altra piattaforma? Importa i tuoi dati per iniziare.',
    'onboarding_import_btn' => 'Importa Dati',
    'onboarding_start_fresh' => 'Inizia da Zero',
    'onboarding_do_later' => 'Fai Più Tardi',

    // Rollback / Undo
    'rollback_btn' => 'Annulla importazione',
    'rollback_confirm' => 'Sei sicuro? Tutti i record importati in questa sessione verranno eliminati definitivamente.',
    'rollback_complete' => ':deleted record importati sono stati eliminati.',
    'rollback_partial' => ':deleted record eliminati, ma alcuni non hanno potuto essere annullati.',
    'rollback_not_available' => 'Questa sessione non può essere annullata.',

    // Dashboard import banner
    'import_in_progress' => 'Un\'importazione dati è in corso. I dati della dashboard potrebbero essere incompleti fino al termine dell\'importazione.',
    'import_in_progress_link' => 'Visualizza stato importazione',

    // Platform WIP
    'platform_wip' => '(Prossimamente)',

    // ── Additional translations ──────────────────────────────
    'opening_balances_entry' => 'Saldi di apertura',
];
