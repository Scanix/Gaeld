<?php

return [
    // Page titles
    'migration' => 'Data Migration',
    'migration_description' => 'Import your existing accounting data from another platform to get started quickly.',
    'new_import' => 'New Import',
    'import_history' => 'Import History',

    // Platform selection
    'select_platform' => 'Select Source Platform',
    'select_platform_desc' => 'Choose the platform you are migrating from.',
    'supported_types' => 'Supported data types',
    'accepted_formats' => 'Accepted formats',

    // Platform labels
    'platform_bexio' => 'Bexio',
    'platform_bexio_desc' => 'Import from Bexio CSV/XLS exports.',
    'platform_banana' => 'Banana Comptabilité',
    'platform_banana_desc' => 'Import from Banana TXT/CSV exports.',
    'platform_abacus' => 'Abacus',
    'platform_abacus_desc' => 'Import from Abacus CSV/XML exports.',
    'platform_generic_csv' => 'Generic CSV',
    'platform_generic_csv_desc' => 'Import from any CSV file with manual column mapping.',
    'platform_manual' => 'Manual Entry',
    'platform_manual_desc' => 'Enter your data manually with a guided wizard.',

    // Data types
    'data_types' => [
        'accounts' => 'Chart of Accounts',
        'opening_balances' => 'Opening Balances',
        'journal_entries' => 'Journal Entries',
        'contacts' => 'Contacts',
        'invoices' => 'Invoices',
        'expenses' => 'Expenses',
        'fixed_assets' => 'Fixed Assets',
        'year_end_closing' => 'Year-End Closing',
    ],

    // Wizard steps
    'step_platform' => 'Platform',
    'step_upload' => 'Upload',
    'step_preview' => 'Preview',
    'step_import' => 'Import',

    // Upload step
    'upload_files' => 'Upload Files',
    'upload_files_desc' => 'Upload your export files for each data type you want to import.',
    'select_data_type' => 'Data Type',
    'upload_file' => 'Upload File',
    'file_parsed' => ':count rows parsed successfully.',
    'parsing_errors' => 'Parsing errors',

    // Column mapping (generic CSV)
    'column_mapping' => 'Column Mapping',
    'column_mapping_desc' => 'Map the columns from your CSV file to the expected fields.',
    'csv_column' => 'CSV Column',
    'maps_to' => 'Maps to',
    'delimiter' => 'Delimiter',

    // Preview step
    'preview_data' => 'Data Preview',
    'preview_desc' => 'Review parsed data before importing.',
    'total_rows' => 'Total rows',
    'valid_rows' => 'Valid rows',
    'invalid_rows' => 'Invalid rows',
    'row_errors' => 'Row errors',
    'row' => 'Row',

    // Account mapping
    'account_mapping' => 'Account Mapping',
    'account_mapping_desc' => 'Review how source accounts are mapped to your chart of accounts.',
    'source_account' => 'Source Account',
    'target_account' => 'Target Account',
    'confidence' => 'Confidence',
    'high_confidence' => 'High',
    'medium_confidence' => 'Medium',
    'low_confidence' => 'Low',
    'no_match' => 'No match',
    'select_account' => 'Select account…',

    // Import step
    'execute_import' => 'Import Data',
    'execute_import_desc' => 'Import the validated data into your organization.',
    'importing' => 'Importing…',
    'import_complete' => ':count data types imported successfully.',
    'import_partial' => ':success imported, :failed failed.',
    'import_queued' => 'Import queued for processing. You can check the status on this page.',
    'imported_count' => ':count imported',
    'skipped_count' => ':count skipped',
    'failed_count' => ':count failed',

    // Results
    'import_results' => 'Import Results',
    'all_done' => 'All Done!',
    'all_done_desc' => 'Your data has been imported successfully. You can now start using Gäld.',
    'go_to_dashboard' => 'Go to Dashboard',
    'start_another' => 'Start Another Import',

    // Status badges
    'status_pending' => 'Pending',
    'status_validating' => 'Validating',
    'status_importing' => 'Importing',
    'status_completed' => 'Completed',
    'status_failed' => 'Failed',
    'status_partially_completed' => 'Partially Completed',

    // Session
    'session_started' => 'Migration session started.',
    'session_deleted' => 'Migration session deleted.',
    'delete_session' => 'Delete Session',
    'delete_session_confirm' => 'Are you sure you want to delete this migration session?',
    'no_sessions' => 'No previous imports.',
    'created_on' => 'Created on',
    'expired_upload' => 'Parsed data for :type has expired. Please upload the file again.',

    // Errors
    'no_data_uploaded' => 'Please upload at least one file before importing.',
    'platform_not_supported' => 'This platform does not support the selected data type.',

    // Onboarding
    'onboarding_import_title' => 'Import Existing Data',
    'onboarding_import_desc' => 'Migrating from another platform? Import your data to get started.',
    'onboarding_import_btn' => 'Import Data',
    'onboarding_start_fresh' => 'Start Fresh',
    'onboarding_do_later' => 'Do This Later',

    // Rollback / Undo
    'rollback_btn' => 'Undo Import',
    'rollback_confirm' => 'Are you sure? This will permanently delete all records imported in this session.',
    'rollback_complete' => ':deleted imported records have been deleted.',
    'rollback_partial' => ':deleted records deleted, but some could not be reversed.',
    'rollback_not_available' => 'This session cannot be reversed.',

    // Dashboard import banner
    'import_in_progress' => 'A data import is currently in progress. Dashboard data may be incomplete until the import finishes.',
    'import_in_progress_link' => 'View Import Status',

    // Platform WIP
    'platform_wip' => '(Coming Soon)',
];
