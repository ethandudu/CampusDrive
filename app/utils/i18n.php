<?php

const DEFAULT_LOCALE = 'fr';
const SUPPORTED_LOCALES = ['fr' => 'Français', 'en' => 'English'];

function locale(): string
{
    $locale = $_SESSION['locale'] ?? DEFAULT_LOCALE;

    return array_key_exists($locale, SUPPORTED_LOCALES) ? $locale : DEFAULT_LOCALE;
}

/**
 * Returns the full translations map, keyed by locale then by translation key.
 * Exposed so translation completeness can be verified (e.g. in tests).
 */
function translations(): array
{
    static $translations = [
        'fr' => [
            'login' => 'Connexion', 'email' => 'Adresse e-mail', 'password' => 'Mot de passe',
            'sign_in' => 'Se connecter', 'create_account' => 'Créer un compte',
            'invalid_credentials' => 'Adresse e-mail ou mot de passe incorrect.',
            'registration_success' => 'Inscription réussie ! Vous pouvez maintenant vous connecter.',
            'register' => 'Inscription', 'create_student_account' => 'Créer un compte étudiant',
            'invitation_recognized' => 'Invitation reconnue. Veuillez choisir un mot de passe pour finaliser votre inscription.',
            'invalid_invitation' => 'Le lien d’invitation est invalide ou a déjà été utilisé.',
            'verification_code' => 'Code de vérification', 'refresh' => 'Actualiser',
            'captcha_placeholder' => 'Saisissez le code de l’image', 'sign_up' => 'S’inscrire',
            'already_have_account' => 'Déjà un compte ? Se connecter',
            'captcha_invalid' => 'Le code de vérification est incorrect.',
            'invitation_email_mismatch' => 'L’adresse e-mail doit correspondre à celle de l’invitation.',
            'settings' => 'Paramètres', 'home' => 'Accueil', 'delegate_area' => 'Espace délégué',
            'logout' => 'Déconnexion', 'account' => 'Mon compte', 'current_role' => 'Rôle actuel',
            'promotion_area' => 'Espace de promotion', 'area_status' => 'Statut de l’espace',
            'active' => 'Actif', 'pending_approval' => 'En attente de validation',
            'no_promotion' => 'Vous n’êtes rattaché à aucune promotion actuellement.',
            'request_promotion' => 'Demander l’ouverture d’un espace (Devenir délégué)',
            'request_promotion_help' => 'En créant un espace, vous en deviendrez le délégué une fois celui-ci validé par l’administration.',
            'promotion_name' => 'Nom de la promotion', 'promotion_placeholder' => 'Ex. : Master 1 Info',
            'submit_request' => 'Soumettre la demande',
            'promotion_already_assigned' => 'Vous êtes déjà rattaché à une promotion.',
            'promotion_request_success' => 'Demande envoyée ! Dès validation par l’admin, vous deviendrez le délégué de cet espace.',
            'promotion_request_error' => 'Erreur lors de la demande.',
            'promotion_inactive' => 'Votre espace de promotion n’est pas encore validé par l’administration.',
            'generic_error' => 'Une erreur est survenue.',
            'language' => 'Langue', 'language_help' => 'Choisissez la langue de l’interface.',
            'save' => 'Enregistrer', 'language_updated' => 'Langue mise à jour.',
            'invalid_language' => 'La langue sélectionnée n’est pas prise en charge.',
            'change_password' => 'Modifier mon mot de passe', 'new_password' => 'Nouveau mot de passe',
            'confirm_password' => 'Confirmer le nouveau mot de passe',
            'unauthorized_access' => 'Accès non autorisé.', 'file_not_found' => 'Fichier introuvable.',
            'file_access_denied' => 'Vous n’avez pas accès à ce fichier.',
            'file_pending_approval' => 'Ce fichier est en attente d’approbation.',
            'file_missing' => 'Le fichier physique n’existe plus sur le serveur.',
            'student_area' => 'Mon espace', 'share_document' => 'Partager un document',
            'select_file' => 'Sélectionner un fichier', 'formats' => 'Formats : PDF, images, vidéos (10 Mo max.).',
            'submit_for_approval' => 'Envoyer pour validation', 'pending_uploads' => 'Mes envois en attente',
            'documents' => 'Documents de la promotion', 'no_documents' => 'Aucun document validé disponible pour le moment.',
            'file_name' => 'Nom du fichier', 'shared_by' => 'Partagé par', 'date' => 'Date',
            'action' => 'Action', 'view' => 'Consulter', 'pending' => 'En attente',
            'file_uploaded' => 'Fichier envoyé avec succès ! Il sera visible une fois validé par votre délégué.',
            'file_save_error' => 'Erreur lors de l’enregistrement du fichier sur le serveur.',
            'file_type_error' => 'Format non autorisé. Seuls les PDF, images (JPG, PNG, GIF) et vidéos (MP4, WEBM) sont acceptés.',
            'file_upload_error' => 'Erreur lors du transfert du fichier.',
            'invite_student' => 'Inviter un étudiant', 'student_email' => 'Adresse e-mail de l’étudiant',
            'generate_invitation' => 'Générer l’invitation', 'sent_invitations' => 'Invitations envoyées',
            'status' => 'Statut', 'registered' => 'Inscrit', 'no_invitations' => 'Aucune invitation envoyée.',
            'files_pending' => 'Fichiers en attente de validation', 'no_files_pending' => 'Aucun fichier en attente.',
            'file' => 'Fichier', 'author' => 'Auteur', 'preview' => 'Aperçu', 'approve' => 'Valider',
            'reject' => 'Refuser', 'folders' => 'Arborescence des dossiers', 'create_folder' => 'Créer un dossier',
            'name' => 'Nom', 'created_at' => 'Créé le', 'new_folder' => 'Créer un nouveau dossier',
            'folder_name' => 'Nom du dossier', 'cancel' => 'Annuler', 'delete_item' => 'Supprimer un élément',
            'delete_confirmation' => 'Êtes-vous sûr de vouloir supprimer cet élément ?', 'delete' => 'Supprimer',
            'back' => 'Retour', 'root' => 'Racine', 'create_folder_in' => 'Créer un dossier dans "%name%"',
            'empty_folder' => 'Aucun fichier ni dossier dans cet emplacement.',
            'administration' => 'Administration', 'pending_promotions' => 'Promotions en attente d’approbation',
            'no_pending_promotions' => 'Aucune demande en attente. C’est bien calme !',
            'promotion_request_date' => 'Date de demande', 'promotion_activated' => 'L’espace de promotion a été activé et l’étudiant demandeur a été nommé délégué.',
            'security_error' => 'Erreur de sécurité : jeton CSRF invalide.',
            'student' => 'Étudiant', 'delegate' => 'Délégué', 'admin' => 'Administrateur',
            'invalid_email_domain' => 'Domaine e-mail invalide. Veuillez utiliser un e-mail d’une université autorisée.',
            'email_already_invited' => 'Cette adresse e-mail a déjà été invitée.',
            'register_message' => 'Ne créez un compte que si vous souhaitez être responsable de la promotion. Sinon, attendez que le délégué vous invite et utilisez le lien d’invitation.',
            'password_mismatch' => 'Le mot de passe et sa confirmation ne correspondent pas.',
            'password_change_success' => 'Mot de passe modifié avec succès ! Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.',
            'password_change_error' => 'Erreur lors de la modification du mot de passe.',
            'current_password' => 'Mot de passe actuel',
        ],
        'en' => [
            'login' => 'Sign in', 'email' => 'Email address', 'password' => 'Password',
            'sign_in' => 'Sign in', 'create_account' => 'Create an account',
            'invalid_credentials' => 'Incorrect email address or password.',
            'registration_success' => 'Registration complete! You can now sign in.',
            'register' => 'Register', 'create_student_account' => 'Create a student account',
            'invitation_recognized' => 'Invitation recognized. Choose a password to complete your registration.',
            'invalid_invitation' => 'The invitation link is invalid or has already been used.',
            'verification_code' => 'Verification code', 'refresh' => 'Refresh',
            'captcha_placeholder' => 'Enter the code shown in the image', 'sign_up' => 'Sign up',
            'already_have_account' => 'Already have an account? Sign in',
            'captcha_invalid' => 'The verification code is incorrect.',
            'invitation_email_mismatch' => 'The email address must match the invitation.',
            'settings' => 'Settings', 'home' => 'Home', 'delegate_area' => 'Delegate area',
            'logout' => 'Sign out', 'account' => 'My account', 'current_role' => 'Current role',
            'promotion_area' => 'Promotion area', 'area_status' => 'Area status',
            'active' => 'Active', 'pending_approval' => 'Awaiting approval',
            'no_promotion' => 'You are not currently assigned to a promotion.',
            'request_promotion' => 'Request a workspace (become a delegate)',
            'request_promotion_help' => 'By creating a workspace, you will become its delegate once it is approved by the administration.',
            'promotion_name' => 'Promotion name', 'promotion_placeholder' => 'e.g. Master 1 Computer Science',
            'submit_request' => 'Submit request',
            'promotion_already_assigned' => 'You are already assigned to a promotion.',
            'promotion_request_success' => 'Request sent! Once approved by an administrator, you will become this workspace’s delegate.',
            'promotion_request_error' => 'An error occurred while submitting the request.',
            'promotion_inactive' => 'Your promotion workspace has not yet been approved by the administration.',
            'generic_error' => 'An error occurred.',
            'language' => 'Language', 'language_help' => 'Choose the interface language.',
            'save' => 'Save', 'language_updated' => 'Language updated.',
            'invalid_language' => 'The selected language is not supported.',
            'change_password' => 'Change my password',
            'unauthorized_access' => 'Unauthorized access.', 'file_not_found' => 'File not found.',
            'file_access_denied' => 'You do not have access to this file.',
            'file_pending_approval' => 'This file is awaiting approval.',
            'file_missing' => 'The physical file no longer exists on the server.',
            'student_area' => 'My area', 'share_document' => 'Share a document',
            'select_file' => 'Select a file', 'formats' => 'Formats: PDF, images, videos (10 MB max.).',
            'submit_for_approval' => 'Submit for approval', 'pending_uploads' => 'My pending uploads',
            'documents' => 'Promotion documents', 'no_documents' => 'No approved documents are currently available.',
            'file_name' => 'File name', 'shared_by' => 'Shared by', 'date' => 'Date',
            'action' => 'Action', 'view' => 'View', 'pending' => 'Pending',
            'file_uploaded' => 'File uploaded successfully! It will be visible once approved by your delegate.',
            'file_save_error' => 'An error occurred while saving the file on the server.',
            'file_type_error' => 'Unsupported format. Only PDF, images (JPG, PNG, GIF), and videos (MP4, WEBM) are accepted.',
            'file_upload_error' => 'An error occurred while uploading the file.',
            'invite_student' => 'Invite a student', 'student_email' => 'Student email address',
            'generate_invitation' => 'Generate invitation', 'sent_invitations' => 'Sent invitations',
            'status' => 'Status', 'registered' => 'Registered', 'no_invitations' => 'No invitations have been sent.',
            'files_pending' => 'Files awaiting approval', 'no_files_pending' => 'No files are awaiting approval.',
            'file' => 'File', 'author' => 'Author', 'preview' => 'Preview', 'approve' => 'Approve',
            'reject' => 'Reject', 'folders' => 'Folder tree', 'create_folder' => 'Create folder',
            'name' => 'Name', 'created_at' => 'Created', 'new_folder' => 'Create a new folder',
            'folder_name' => 'Folder name', 'cancel' => 'Cancel', 'delete_item' => 'Delete item',
            'delete_confirmation' => 'Are you sure you want to delete this item?', 'delete' => 'Delete',
            'back' => 'Back', 'root' => 'Root', 'create_folder_in' => 'Create a folder in "%name%"',
            'empty_folder' => 'There are no files or folders here.',
            'administration' => 'Administration', 'pending_promotions' => 'Promotions awaiting approval',
            'no_pending_promotions' => 'No requests are awaiting approval. It is very quiet!',
            'promotion_request_date' => 'Request date', 'promotion_activated' => 'The promotion workspace has been activated and the requesting student has been made a delegate.',
            'security_error' => 'Security error: invalid CSRF token.',
            'student' => 'Student', 'delegate' => 'Delegate', 'admin' => 'Administrator',
            'invalid_email_domain' => 'Invalid email domain. Please use an university email.',
            'email_already_invited' => 'This email address has already been invited.',
            'register_message' => 'Only create an account if you want to be responsible for the promotion. Otherwise, wait for the delegate to invite you and use the invitation link.',
            'current_password' => 'Current password',
            'new_password' => 'New password',
            'confirm_password' => 'Confirm password',
            'password_mismatch' => 'The password and its confirmation do not match.',
            'password_change_success' => 'Password changed successfully! You can now sign in with your new password.',
            'password_change_error' => 'An error occurred while changing the password.',
        ],
    ];

    return $translations;
}

function t(string $key, array $replacements = []): string
{
    $translations = translations();

    $text = $translations[locale()][$key] ?? $translations[DEFAULT_LOCALE][$key] ?? $key;

    foreach ($replacements as $name => $value) {
        $text = str_replace('%' . $name . '%', (string) $value, $text);
    }

    return $text;
}
