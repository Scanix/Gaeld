<?php

return [
    // Page titles
    'migration' => 'Datenmigration',
    'migration_description' => 'Importieren Sie Ihre bestehenden Buchhaltungsdaten von einer anderen Plattform, um schnell loszulegen.',
    'new_import' => 'Neuer Import',
    'import_history' => 'Importverlauf',

    // Platform selection
    'select_platform' => 'Quellplattform Auswählen',
    'select_platform_desc' => 'Wählen Sie die Plattform, von der Sie migrieren.',
    'supported_types' => 'Unterstützte Datentypen',
    'accepted_formats' => 'Akzeptierte Formate',

    // Platform labels
    'platform_bexio' => 'Bexio',
    'platform_bexio_desc' => 'Import von Bexio CSV/XLS-Exporten.',
    'platform_banana' => 'Banana Buchhaltung',
    'platform_banana_desc' => 'Import von Banana TXT/CSV-Exporten.',
    'platform_abacus' => 'Abacus',
    'platform_abacus_desc' => 'Import von Abacus CSV/XML-Exporten.',
    'platform_generic_csv' => 'Allgemeines CSV',
    'platform_generic_csv_desc' => 'Import von jeder CSV-Datei mit manueller Spaltenzuordnung.',
    'platform_manual' => 'Manuelle Eingabe',
    'platform_manual_desc' => 'Geben Sie Ihre Daten manuell mit geführtem Assistenten ein.',

    // Data types
    'data_types' => [
        'accounts' => 'Kontenplan',
        'opening_balances' => 'Eröffnungssalden',
        'journal_entries' => 'Buchungen',
        'contacts' => 'Kontakte',
        'invoices' => 'Rechnungen',
        'expenses' => 'Ausgaben',
        'fixed_assets' => 'Anlagevermögen',
        'year_end_closing' => 'Jahresabschluss',
    ],

    // Wizard steps
    'step_platform' => 'Plattform',
    'step_upload' => 'Hochladen',
    'step_preview' => 'Vorschau',
    'step_import' => 'Import',

    // Upload step
    'upload_files' => 'Dateien Hochladen',
    'upload_files_desc' => 'Laden Sie Ihre Exportdateien für jeden Datentyp hoch, den Sie importieren möchten.',
    'select_data_type' => 'Datentyp',
    'upload_file' => 'Datei Hochladen',
    'file_parsed' => ':count Zeilen erfolgreich analysiert.',
    'parsing_errors' => 'Analysefehler',

    // Column mapping (generic CSV)
    'column_mapping' => 'Spaltenzuordnung',
    'column_mapping_desc' => 'Ordnen Sie die Spalten Ihrer CSV-Datei den erwarteten Feldern zu.',
    'csv_column' => 'CSV-Spalte',
    'maps_to' => 'Zugeordnet zu',
    'delimiter' => 'Trennzeichen',

    // Preview step
    'preview_data' => 'Datenvorschau',
    'preview_desc' => 'Überprüfen Sie die analysierten Daten vor dem Import.',
    'total_rows' => 'Gesamtzeilen',
    'valid_rows' => 'Gültige Zeilen',
    'invalid_rows' => 'Ungültige Zeilen',
    'row_errors' => 'Zeilenfehler',
    'row' => 'Zeile',

    // Account mapping
    'account_mapping' => 'Kontozuordnung',
    'account_mapping_desc' => 'Überprüfen Sie, wie Quellkonten Ihrem Kontenplan zugeordnet werden.',
    'source_account' => 'Quellkonto',
    'target_account' => 'Zielkonto',
    'confidence' => 'Konfidenz',
    'high_confidence' => 'Hoch',
    'medium_confidence' => 'Mittel',
    'low_confidence' => 'Niedrig',
    'no_match' => 'Keine Übereinstimmung',
    'select_account' => 'Konto auswählen…',

    // Import step
    'execute_import' => 'Daten Importieren',
    'execute_import_desc' => 'Importieren Sie die validierten Daten in Ihre Organisation.',
    'importing' => 'Importiere…',
    'import_complete' => ':count Datentypen erfolgreich importiert.',
    'import_partial' => ':success importiert, :failed fehlgeschlagen.',
    'import_queued' => 'Der Import wurde in die Warteschlange gestellt. Sie können den Status auf dieser Seite überprüfen.',
    'imported_count' => ':count importiert',
    'skipped_count' => ':count übersprungen',
    'failed_count' => ':count fehlgeschlagen',

    // Results
    'import_results' => 'Importergebnisse',
    'all_done' => 'Fertig!',
    'all_done_desc' => 'Ihre Daten wurden erfolgreich importiert. Sie können jetzt Gäld verwenden.',
    'go_to_dashboard' => 'Zum Dashboard',
    'start_another' => 'Weiteren Import Starten',

    // Status badges
    'status_pending' => 'Ausstehend',
    'status_validating' => 'Überprüfung',
    'status_importing' => 'Importiert',
    'status_completed' => 'Abgeschlossen',
    'status_failed' => 'Fehlgeschlagen',
    'status_partially_completed' => 'Teilweise Abgeschlossen',

    // Session
    'session_started' => 'Migrationssitzung gestartet.',
    'session_deleted' => 'Migrationssitzung gelöscht.',
    'delete_session' => 'Sitzung Löschen',
    'delete_session_confirm' => 'Sind Sie sicher, dass Sie diese Migrationssitzung löschen möchten?',
    'no_sessions' => 'Keine vorherigen Importe.',
    'created_on' => 'Erstellt am',
    'expired_upload' => 'Die analysierten Daten für :type sind abgelaufen. Bitte laden Sie die Datei erneut hoch.',

    // Errors
    'no_data_uploaded' => 'Bitte laden Sie mindestens eine Datei hoch, bevor Sie importieren.',
    'platform_not_supported' => 'Diese Plattform unterstützt den ausgewählten Datentyp nicht.',

    // Onboarding
    'onboarding_import_title' => 'Bestehende Daten Importieren',
    'onboarding_import_desc' => 'Migrieren Sie von einer anderen Plattform? Importieren Sie Ihre Daten, um loszulegen.',
    'onboarding_import_btn' => 'Daten Importieren',
    'onboarding_start_fresh' => 'Neu Beginnen',
    'onboarding_do_later' => 'Später Erledigen',

    // Rollback / Undo
    'rollback_btn' => 'Import rückgängig machen',
    'rollback_confirm' => 'Sind Sie sicher? Alle in dieser Sitzung importierten Datensätze werden dauerhaft gelöscht.',
    'rollback_complete' => ':deleted importierte Datensätze wurden gelöscht.',
    'rollback_partial' => ':deleted Datensätze gelöscht, aber einige konnten nicht rückgängig gemacht werden.',
    'rollback_not_available' => 'Diese Sitzung kann nicht rückgängig gemacht werden.',

    // Dashboard import banner
    'import_in_progress' => 'Ein Datenimport wird gerade durchgeführt. Die Dashboard-Daten können unvollständig sein, bis der Import abgeschlossen ist.',
    'import_in_progress_link' => 'Importstatus anzeigen',

    // Platform WIP
    'platform_wip' => '(Demnächst verfügbar)',

    // ── Additional translations ──────────────────────────────
    'opening_balances_entry' => 'Eröffnungssalden',
];
