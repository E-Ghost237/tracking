<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

/**
 * Email templates per event and language (FR-110). Admins edit them in the back-office.
 * Templates never contain payment account details or card codes (FR-113).
 */
class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $event => $locales) {
            foreach ($locales as $locale => [$subject, $body]) {
                NotificationTemplate::query()->firstOrCreate(
                    ['event' => $event, 'locale' => $locale],
                    ['subject' => $subject, 'body' => $body, 'is_active' => true],
                );
            }
        }
    }

    /**
     * @return array<string, array<string, array{0: string, 1: string}>>
     */
    private function templates(): array
    {
        return [
            'account.verify' => [
                'en' => ['Confirm your email address', "Hello {{name}},\n\nWelcome to {{brand}}. Please confirm your email address to start booking shipments.\n\n[Confirm my email]({{verify_url}})\n\nThis link expires in 60 minutes. If you did not create an account, you can ignore this email."],
                'fr' => ['Confirmez votre adresse e-mail', "Bonjour {{name}},\n\nBienvenue chez {{brand}}. Confirmez votre adresse e-mail pour commencer à réserver vos expéditions.\n\n[Confirmer mon e-mail]({{verify_url}})\n\nCe lien expire dans 60 minutes. Si vous n'avez pas créé de compte, ignorez cet e-mail."],
            ],
            'account.password_reset' => [
                'en' => ['Reset your password', "Hello {{name}},\n\nWe received a request to reset your password.\n\n[Choose a new password]({{reset_url}})\n\nThe link works once and expires in {{minutes}} minutes. If you did not ask for this, ignore this email: your password stays the same."],
                'fr' => ['Réinitialisez votre mot de passe', "Bonjour {{name}},\n\nNous avons reçu une demande de réinitialisation de votre mot de passe.\n\n[Choisir un nouveau mot de passe]({{reset_url}})\n\nLe lien fonctionne une seule fois et expire dans {{minutes}} minutes. Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail."],
            ],
            'account.register_existing' => [
                'en' => ['Someone tried to create an account with your email', "Hello {{name}},\n\nSomeone tried to create a new {{brand}} account with this email address. You already have an account.\n\n[Sign in]({{login_url}}) · [Reset your password]({{reset_url}})\n\nIf this was not you, no action is needed."],
                'fr' => ['Tentative de création de compte avec votre e-mail', "Bonjour {{name}},\n\nQuelqu'un a essayé de créer un compte {{brand}} avec cette adresse e-mail. Vous avez déjà un compte.\n\n[Se connecter]({{login_url}}) · [Réinitialiser le mot de passe]({{reset_url}})\n\nSi ce n'était pas vous, aucune action n'est nécessaire."],
            ],
            'security.new_login' => [
                'en' => ['New sign-in to your account', "Hello {{name}},\n\nYour account was used to sign in from a new device.\n\n- Time: {{time}}\n- IP address: {{ip}}\n- Device: {{device}}\n\nIf this was you, there is nothing to do. If not, [change your password]({{security_url}}) and turn on two-factor authentication."],
                'fr' => ['Nouvelle connexion à votre compte', "Bonjour {{name}},\n\nVotre compte a été utilisé depuis un nouvel appareil.\n\n- Date : {{time}}\n- Adresse IP : {{ip}}\n- Appareil : {{device}}\n\nSi c'était vous, il n'y a rien à faire. Sinon, [changez votre mot de passe]({{security_url}}) et activez la double authentification."],
            ],
            'security.password_changed' => [
                'en' => ['Your password was changed', "Hello {{name}},\n\nThe password of your {{brand}} account was changed on {{time}}.\n\nIf you did not do this, contact us immediately at {{support_email}}."],
                'fr' => ['Votre mot de passe a été modifié', "Bonjour {{name}},\n\nLe mot de passe de votre compte {{brand}} a été modifié le {{time}}.\n\nSi vous n'êtes pas à l'origine de ce changement, contactez-nous immédiatement à {{support_email}}."],
            ],
            'booking.created' => [
                'en' => ['Order {{order_number}}: payment due', "Hello {{name}},\n\nYour shipment is booked. To release your label and tracking number, please pay **{{amount}}** before **{{expires_at}}**.\n\nPayment reference: **{{reference}}** (write it in your payment note).\n\n[Choose a payment method]({{pay_url}})\n\nPayment details are shown only in your account, after you choose a method."],
                'fr' => ['Commande {{order_number}} : paiement attendu', "Bonjour {{name}},\n\nVotre expédition est réservée. Pour obtenir votre étiquette et votre numéro de suivi, réglez **{{amount}}** avant le **{{expires_at}}**.\n\nRéférence de paiement : **{{reference}}** (à indiquer dans le motif du paiement).\n\n[Choisir un moyen de paiement]({{pay_url}})\n\nLes coordonnées de paiement s'affichent uniquement dans votre espace client, après le choix du moyen de paiement."],
            ],
            'payment.reminder' => [
                'en' => ['Reminder: order {{order_number}} expires in {{hours}} hours', "Hello {{name}},\n\nWe have not received a proof of payment for order {{order_number}} ({{amount}}). The order expires in about **{{hours}} hours**.\n\n[Pay and upload my proof]({{pay_url}})"],
                'fr' => ['Rappel : la commande {{order_number}} expire dans {{hours}} heures', "Bonjour {{name}},\n\nNous n'avons pas encore reçu de preuve de paiement pour la commande {{order_number}} ({{amount}}). Elle expire dans environ **{{hours}} heures**.\n\n[Payer et envoyer ma preuve]({{pay_url}})"],
            ],
            'proof.received' => [
                'en' => ['We received your proof of payment', "Hello {{name}},\n\nThank you. Your proof of payment for order {{order_number}} is in our review queue. We aim to review it within {{review_minutes}} minutes during staffed hours.\n\n[View the order]({{order_url}})"],
                'fr' => ['Nous avons reçu votre preuve de paiement', "Bonjour {{name}},\n\nMerci. Votre preuve de paiement pour la commande {{order_number}} est en cours de vérification. Nous visons un délai de {{review_minutes}} minutes pendant les heures d'ouverture.\n\n[Voir la commande]({{order_url}})"],
            ],
            'proof.approved' => [
                'en' => ['Payment approved: your tracking number {{tracking_number}}', "Hello {{name}},\n\nYour payment for order {{order_number}} is approved (receipt {{receipt_number}}).\n\nTracking number: **{{tracking_number}}**\n\n[Download your label and documents]({{shipment_url}}) · [Track your shipment]({{tracking_url}})"],
                'fr' => ['Paiement validé : votre numéro de suivi {{tracking_number}}', "Bonjour {{name}},\n\nVotre paiement pour la commande {{order_number}} est validé (reçu {{receipt_number}}).\n\nNuméro de suivi : **{{tracking_number}}**\n\n[Télécharger l'étiquette et les documents]({{shipment_url}}) · [Suivre l'expédition]({{tracking_url}})"],
            ],
            'proof.rejected' => [
                'en' => ['Your proof of payment was not accepted', "Hello {{name}},\n\nWe could not accept the proof of payment for order {{order_number}}.\n\nReason: {{reason}}\n\nYou can upload a new proof or choose another payment method.\n\n[Go to payment]({{pay_url}})"],
                'fr' => ["Votre preuve de paiement n'a pas été acceptée", "Bonjour {{name}},\n\nNous n'avons pas pu accepter la preuve de paiement de la commande {{order_number}}.\n\nMotif : {{reason}}\n\nVous pouvez envoyer une nouvelle preuve ou choisir un autre moyen de paiement.\n\n[Aller au paiement]({{pay_url}})"],
            ],
            'proof.more_info' => [
                'en' => ['We need more information about your payment', "Hello {{name}},\n\nAbout order {{order_number}}:\n\n> {{message}}\n\n[Reply by uploading a new proof]({{pay_url}})"],
                'fr' => ["Nous avons besoin d'informations sur votre paiement", "Bonjour {{name}},\n\nAu sujet de la commande {{order_number}} :\n\n> {{message}}\n\n[Envoyer une nouvelle preuve]({{pay_url}})"],
            ],
            'order.expired' => [
                'en' => ['Order {{order_number}} expired', "Hello {{name}},\n\nOrder {{order_number}} expired because no proof of payment was received in time. No payment details remain active for this order.\n\n[Request a new quote]({{quote_url}})"],
                'fr' => ['La commande {{order_number}} a expiré', "Bonjour {{name}},\n\nLa commande {{order_number}} a expiré faute de preuve de paiement reçue à temps.\n\n[Demander un nouveau devis]({{quote_url}})"],
            ],
            'refund.processed' => [
                'en' => ['Refund recorded for order {{order_number}}', "Hello {{name}},\n\nWe recorded a refund of **{{amount}}** for order {{order_number}} via {{method}}. Depending on the method, it can take a few days to reach you."],
                'fr' => ['Remboursement enregistré pour la commande {{order_number}}', "Bonjour {{name}},\n\nNous avons enregistré un remboursement de **{{amount}}** pour la commande {{order_number}} via {{method}}. Selon le moyen utilisé, il peut prendre quelques jours."],
            ],
            'shipment.status_changed' => [
                'en' => ['{{tracking_number}}: {{status}}', "Hello,\n\nShipment **{{tracking_number}}** is now: **{{status}}** {{place}}\n\n[See the full timeline]({{tracking_url}})"],
                'fr' => ['{{tracking_number}} : {{status}}', "Bonjour,\n\nL'expédition **{{tracking_number}}** est maintenant : **{{status}}** {{place}}\n\n[Voir le suivi complet]({{tracking_url}})"],
            ],
            'tracking.confirm_subscription' => [
                'en' => ['Confirm tracking alerts for {{tracking_number}}', "Hello,\n\nPlease confirm that you want email alerts for shipment **{{tracking_number}}**.\n\n[Confirm alerts]({{confirm_url}})\n\nIf you did not ask for this, ignore this email."],
                'fr' => ['Confirmez les alertes de suivi pour {{tracking_number}}', "Bonjour,\n\nConfirmez que vous souhaitez recevoir des alertes pour l'expédition **{{tracking_number}}**.\n\n[Confirmer les alertes]({{confirm_url}})\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail."],
            ],
            'ticket.reply' => [
                'en' => ['Re: {{subject}}', "Hello {{name}},\n\nOur team replied to your request:\n\n> {{message}}\n\n[View the conversation]({{ticket_url}})"],
                'fr' => ['Re : {{subject}}', "Bonjour {{name}},\n\nNotre équipe a répondu à votre demande :\n\n> {{message}}\n\n[Voir la conversation]({{ticket_url}})"],
            ],
            'claim.update' => [
                'en' => ['Claim update for {{tracking_number}}: {{status}}', "Hello {{name}},\n\nYour claim for shipment {{tracking_number}} is now **{{status}}**.\n\n{{message}}"],
                'fr' => ['Réclamation {{tracking_number}} : {{status}}', "Bonjour {{name}},\n\nVotre réclamation pour l'expédition {{tracking_number}} est maintenant **{{status}}**.\n\n{{message}}"],
            ],
            'admin.proof_waiting' => [
                'en' => ['New proof to review: {{order_number}}', "A proof of payment is waiting for review.\n\n- Order: {{order_number}}\n- Method: {{method}}\n- Amount declared: {{amount}}\n\n[Open the review screen]({{review_url}})"],
            ],
            'admin.proof_overdue' => [
                'en' => ['Proof waiting for more than {{minutes}} minutes: {{order_number}}', "The proof for order {{order_number}} has been waiting for **{{minutes}} minutes**.\n\n[Review it now]({{review_url}})"],
            ],
            'admin.order_escalated' => [
                'en' => ['Order {{order_number}} escalated after {{attempts}} rejected proofs', 'Order {{order_number}} has {{attempts}} rejected proofs and needs an admin decision.'],
            ],
            'admin.new_ticket' => [
                'en' => ['New support request: {{subject}}', "A new support request was received from {{from}}.\n\nSubject: {{subject}}"],
            ],
            'admin.claim_opened' => [
                'en' => ['New claim ({{type}}) for {{tracking_number}}', 'A customer opened a {{type}} claim for shipment {{tracking_number}}.'],
            ],
            'admin.refund_needed' => [
                'en' => ['Paid order {{order_number}} cancelled: refund to process', 'The customer cancelled paid order {{order_number}} before pickup. Record the refund in the back-office.'],
            ],
            'admin.deletion_request' => [
                'en' => ['Account deletion request: {{email}}', 'The customer {{email}} asked for account deletion. Process it within 30 days (GDPR).'],
            ],
            'admin.payment_method_changed' => [
                'en' => ['Payment method changed: {{method}}', "The account details of payment method **{{method}}** were changed by {{actor}} on {{time}}.\n\nIf you did not expect this change, review the audit log immediately."],
            ],
            'admin.daily_summary' => [
                'en' => ['Daily summary {{date}}', "- Proofs approved: {{approved}}\n- Proofs rejected: {{rejected}}\n- Proofs waiting: {{waiting}}\n- Orders expired: {{expired}}\n- Failed jobs: {{failed_jobs}}"],
            ],
        ];
    }
}
