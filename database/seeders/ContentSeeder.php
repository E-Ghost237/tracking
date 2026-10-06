<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Starter CMS content in English and French. Legal pages are templates that must be
 * reviewed by a lawyer in each target market before launch (section 12).
 */
class ContentSeeder extends Seeder
{
    private const LEGAL_NOTE_EN = "> Template text. This page must be reviewed by a qualified lawyer for each target market before launch.\n\n";

    private const LEGAL_NOTE_FR = "> Texte modèle. Cette page doit être relue par un juriste qualifié pour chaque marché avant la mise en ligne.\n\n";

    public function run(): void
    {
        foreach ($this->pages() as $slug => $locales) {
            foreach ($locales as $locale => [$title, $summary, $body]) {
                Page::query()->firstOrCreate(['slug' => $slug, 'locale' => $locale], [
                    'title' => $title,
                    'summary' => $summary,
                    'body' => $body,
                    'is_published' => true,
                    'published_at' => now()->subDay(),
                ]);
            }
        }

        if (Faq::query()->exists()) {
            return;
        }

        foreach ($this->faqs() as $locale => $items) {
            foreach ($items as $index => [$category, $question, $answer]) {
                Faq::query()->create(['locale' => $locale, 'category' => $category, 'question' => $question, 'answer' => $answer, 'sort_order' => $index, 'is_published' => true]);
            }
        }
    }

    /**
     * @return array<string, array<string, array{0: string, 1: string, 2: string}>>
     */
    private function pages(): array
    {
        $brand = config('platform.brand.name');

        return [
            'customs' => [
                'en' => ['Customs guide', 'A practical guide to declarations, documents, duties and the questions customs teams may ask.', <<<MD
## Start with an accurate description
Every international shipment needs a plain, complete description of what is inside, its quantity, its value and the reason it is being sent. A precise description helps customs teams assess the parcel and reduces the chance of an avoidable hold. Descriptions such as “gift” or “personal items” are not enough on their own.

Include the product name, what it is made from where relevant, how many pieces are in the parcel and the value in the requested currency. Keep the information consistent with any invoice or supporting document you provide.

## Duties and taxes
Import duties, taxes and clearance charges are set by the destination country. They depend on the type of goods, their declared value, origin and local rules. In many cases, the recipient pays these charges before delivery. Check the rules for the destination before sending high-value or regulated goods.

| Destination | What to expect | Helpful detail |
| --- | --- | --- |
| European Union | VAT may apply to imported goods. Customs duty can depend on value and category. | The correct commodity code and declared value help authorities assess the shipment. |
| United States | Some shipments qualify for a low-value exemption, while other goods have additional requirements. | Rules vary by item, origin and current trade measures. |
| Other destinations | Thresholds, taxes and exemptions differ from one country to another. | Check with the destination customs authority before you book. |

## Documents to prepare
- A commercial invoice with a clear description, quantity, value and reason for export.
- An identification document when the route or item requires one.
- Permits, certificates or safety information for goods subject to additional controls.
- Accurate sender and recipient details, including a reachable phone number.

{$brand} can help you understand the documents requested during booking. The sender remains responsible for making sure that the information and paperwork supplied are complete and correct.

## Restricted and prohibited goods
Some items cannot travel by certain modes, and some are not accepted at all. Medicine, lithium batteries, electronics and valuable goods may need extra documents or special handling. Read our [restricted and prohibited items guide](/prohibited-items) before packing your shipment.

## Before you hand over a parcel
Keep a copy of the invoice and any permits, use packaging that protects the contents and answer customs questions promptly. Customs agencies can inspect a shipment at their discretion. If a declaration is incomplete or inaccurate, the parcel may be delayed, returned or refused.
MD],
                'fr' => ['Guide des douanes', 'Un guide pratique sur les déclarations, les documents et les frais à l’importation.', <<<MD
## Commencez par une description précise
Tout envoi international doit indiquer clairement son contenu, la quantité, la valeur et le motif de l’expédition. Une description précise aide les services douaniers à évaluer le colis et limite les blocages évitables. Les mentions « cadeau » ou « effets personnels » ne suffisent pas à elles seules.

Indiquez le nom du produit, sa matière si elle est utile, le nombre d’articles et leur valeur dans la devise demandée. Veillez à ce que ces informations correspondent à la facture et aux autres documents fournis.

## Droits et taxes
Les droits, taxes et frais de dédouanement sont fixés par le pays de destination. Ils dépendent du type de marchandise, de sa valeur déclarée, de son origine et des règles locales. Dans de nombreux cas, le destinataire règle ces frais avant la livraison. Consultez les règles applicables avant d’envoyer un article de valeur ou réglementé.

| Destination | À quoi s’attendre | À retenir |
| --- | --- | --- |
| Union européenne | La TVA peut s’appliquer aux marchandises importées. Les droits de douane dépendent de la valeur et de la catégorie. | Un code douanier et une valeur déclarée exacts facilitent l’évaluation. |
| États-Unis | Certains envois de faible valeur peuvent bénéficier d’une exemption, tandis que d’autres marchandises sont soumises à des exigences supplémentaires. | Les règles varient selon l’article, son origine et les mesures commerciales en vigueur. |
| Autres destinations | Les seuils, taxes et exemptions varient selon les pays. | Vérifiez les règles auprès de l’administration douanière de destination avant de réserver. |

## Documents à préparer
- Une facture commerciale indiquant le contenu, la quantité, la valeur et le motif de l’envoi.
- Une pièce d’identité lorsque la destination ou la marchandise l’exige.
- Les permis, certificats ou informations de sécurité nécessaires pour les biens réglementés.
- Les coordonnées exactes de l’expéditeur et du destinataire, avec un numéro de téléphone joignable.

{$brand} peut vous aider à comprendre les documents demandés au moment de la réservation. L’expéditeur reste responsable de l’exactitude et de l’exhaustivité des informations transmises.

## Articles réglementés et interdits
Certains articles ne peuvent pas voyager par tous les modes, et certains ne sont pas acceptés. Les médicaments, batteries au lithium, appareils électroniques et biens de valeur peuvent nécessiter des documents ou un emballage particuliers. Consultez notre [guide des articles réglementés et interdits](/fr/articles-interdits) avant l’emballage.

## Avant de remettre votre colis
Conservez une copie de la facture et des permis, protégez soigneusement le contenu et répondez sans tarder aux demandes des douanes. Les autorités peuvent contrôler un envoi. Une déclaration incomplète ou inexacte peut entraîner un retard, un retour ou un refus.
MD],
            ],
            'packing' => [
                'en' => ['Packing guide', 'A step-by-step guide to choosing a box, protecting the contents and preparing a clear shipping label.', <<<'MD'
## Choose a box that fits the journey
Use a sturdy, double-wall cardboard box in good condition. It should leave enough room for protective material on every side, without being much larger than the items inside. Remove old labels and barcodes before reusing a box.

For unusually heavy, sharp or fragile items, consider a stronger outer carton or purpose-made packaging. A soft mailer is not suitable for goods that could be crushed or damaged by pressure.

## Protect every item
1. Wrap each item separately so hard surfaces do not rub together.
2. Add cushioning around the sides, corners and top of the parcel.
3. Fill empty space with paper, foam or another suitable material so the contents do not shift when the box is gently moved.
4. Put liquid containers in sealed bags, upright where possible, with absorbent material around them.
5. Keep batteries and electronic devices protected from movement, pressure and accidental activation. Check the restrictions that apply to your route.

## Close and label the parcel
Seal every opening with strong packing tape. Run tape across the centre seam and along both edge seams, then repeat on the other side. Place the shipping label flat on the largest side of the parcel, where it can be read and scanned easily. Do not cover the barcode with tape that causes glare.

If a commercial invoice or customs document is required, attach it in the document pouch or as instructed during booking. Keep a copy for your records.

## Measure after packing
Measure the finished parcel at its widest points and record its final weight. The quote tool compares actual weight with volumetric weight, calculated as length × width × height in centimetres ÷ 5000. A light parcel that takes up a lot of space may be charged by its volume, so use a box that protects the contents without leaving unnecessary space.

## A final check
Give the sealed parcel a gentle shake. If anything moves, add cushioning. Confirm that the recipient's name, address and phone number are correct, and make sure the customs description matches what is actually inside.
MD],
                'fr' => ["Guide d'emballage", 'Les étapes pour choisir un carton, protéger son contenu et préparer une étiquette lisible.', <<<'MD'
## Choisir un carton adapté au trajet
Utilisez un carton solide à double cannelure et en bon état. Laissez assez de place pour protéger chaque face, sans choisir un emballage beaucoup trop grand. Retirez les anciennes étiquettes et les codes-barres avant de réutiliser un carton.

Pour les articles lourds, pointus ou fragiles, choisissez une protection renforcée ou un emballage adapté. Une enveloppe souple ne convient pas aux objets qui risquent d’être écrasés.

## Protéger chaque article
1. Emballez les articles séparément pour éviter les chocs et les frottements.
2. Ajoutez une protection autour des côtés, des angles et du dessus du colis.
3. Comblez les espaces vides avec du papier, de la mousse ou un matériau adapté pour empêcher tout mouvement.
4. Placez les liquides dans des sacs étanches, debout si possible, avec un matériau absorbant.
5. Protégez les batteries et les appareils électroniques contre les chocs, la pression et toute mise en marche accidentelle. Vérifiez les règles applicables à votre trajet.

## Fermer et étiqueter le colis
Fermez chaque ouverture avec un ruban adhésif résistant. Renforcez la jointure centrale et les deux bords, puis répétez l’opération sur l’autre face. Collez l’étiquette à plat sur le plus grand côté, sans pli ni reflet gênant pour le code-barres.

Si une facture commerciale ou un document douanier est nécessaire, placez-le dans la pochette prévue ou suivez les consignes de réservation. Conservez une copie de vos documents.

## Mesurer après l’emballage
Mesurez le colis terminé à ses points les plus larges et pesez-le. L’outil de devis compare le poids réel au poids volumétrique, calculé ainsi : longueur × largeur × hauteur en centimètres ÷ 5000. Un colis léger mais volumineux peut être tarifé selon son volume. Choisissez donc un carton qui protège sans laisser trop d’espace.

## Dernière vérification
Secouez doucement le colis fermé. Si le contenu bouge, ajoutez du calage. Vérifiez le nom, l’adresse et le téléphone du destinataire, puis assurez-vous que la déclaration douanière correspond au contenu réel.
MD],
            ],
            'about' => [
                'en' => ['About us', "{$brand} helps people and businesses move parcels and freight across North America, Europe and supported global routes.", "## Shipping should be understandable\nInternational freight can involve different carriers, paperwork and handoffs. {$brand} brings the practical steps together in one place, so you can compare an available service, understand the estimate and know where to look for an update.\n\n## From the first details to the final delivery\nStart with the route, parcel dimensions and weight. The quote tool uses those details to show an estimate and a typical transit window. If you decide to continue, you can add the sender, recipient and customs information, then follow the shipment as it moves between teams.\n\n## A person is part of the process\nOur team reviews payment proofs before a label is released and can help when a route, document or delivery question needs a closer look. We want customers to know what is happening and what to do next, not spend time guessing.\n\n## Built for real journeys\nFamilies use international freight to send personal belongings and thoughtful gifts. Businesses use it to move samples, equipment and stock. The right service depends on the shipment, deadline and destination, so availability is confirmed for the route you enter.\n\nWe work with established carrier partners for local delivery where needed. Tracking and transit information can vary by route and service, and we show the source of available updates whenever possible."],
                'fr' => ['À propos', "{$brand} accompagne les particuliers et les entreprises pour leurs colis et marchandises en Amérique du Nord, en Europe et sur les itinéraires disponibles à l’international.", "## L’expédition doit être compréhensible\nLe transport international fait intervenir des transporteurs, des documents et plusieurs étapes. {$brand} rassemble les informations utiles au même endroit afin que vous puissiez comparer les services disponibles, comprendre l’estimation et savoir où consulter les mises à jour.\n\n## Des premières informations à la livraison\nCommencez par indiquer le trajet, les dimensions et le poids du colis. L’outil de devis utilise ces éléments pour présenter une estimation et un délai de transit habituel. Si vous souhaitez poursuivre, ajoutez les coordonnées de l’expéditeur et du destinataire ainsi que les informations douanières, puis suivez l’envoi entre les différentes équipes.\n\n## Une équipe reste à vos côtés\nNotre équipe vérifie les preuves de paiement avant la mise à disposition d’une étiquette et peut vous accompagner lorsqu’une question sur un trajet, un document ou une livraison nécessite un examen plus précis. Vous devez pouvoir comprendre la situation et la prochaine étape sans avoir à deviner.\n\n## Des services pensés pour de vrais trajets\nLes particuliers utilisent le transport international pour envoyer des effets personnels ou des cadeaux. Les entreprises s’en servent pour déplacer des échantillons, du matériel et des stocks. Le service adapté dépend du colis, du délai et de la destination. La disponibilité est confirmée pour le trajet indiqué dans le devis.\n\nLorsque cela est nécessaire, nous travaillons avec des transporteurs reconnus pour la livraison locale. Les mises à jour et les délais varient selon le trajet et le service. Nous indiquons leur source dès que ces informations sont disponibles."],
            ],
            'terms' => [
                'en' => ['Terms of service', 'The rules that apply when you use our website and services.', self::LEGAL_NOTE_EN."## 1. Scope\nThese terms apply to quotes, bookings, payments and tracking on this website.\n\n## 2. Quotes and prices\nQuotes are indicative and valid for 7 days. The price is fixed at booking. If the measured weight or size differs from the declaration, the difference is invoiced or refunded.\n\n## 3. Your obligations\nYou must describe contents accurately, pack them properly and never ship prohibited items. You are responsible for the contents of your shipment.\n\n## 4. Payment\nOrders are paid using the methods shown on your order. Your label and tracking number are released only after our team approves your payment. Unpaid orders expire after 48 hours.\n\n## 5. Liability\nOur liability for loss or damage is limited to the declared value when insurance is purchased, and otherwise to the limits set out in the shipping policy.\n\n## 6. Cancellations\nYou may cancel before pickup. Refunds follow the refund policy.\n\n## 7. Governing law\nTo be completed for each market."],
                'fr' => ["Conditions d'utilisation", "Les règles applicables à l'utilisation du site et de nos services.", self::LEGAL_NOTE_FR."## 1. Champ d'application\nCes conditions s'appliquent aux devis, réservations, paiements et au suivi sur ce site.\n\n## 2. Devis et prix\nLes devis sont indicatifs et valables 7 jours. Le prix est figé à la réservation. Si le poids ou les dimensions mesurés diffèrent, l'écart est facturé ou remboursé.\n\n## 3. Vos obligations\nVous devez décrire le contenu avec exactitude, l'emballer correctement et ne jamais expédier d'articles interdits.\n\n## 4. Paiement\nLes commandes se règlent avec les moyens affichés sur votre commande. Votre étiquette et votre numéro de suivi sont libérés après validation du paiement par notre équipe. Les commandes impayées expirent après 48 heures.\n\n## 5. Responsabilité\nNotre responsabilité est limitée à la valeur déclarée en cas d'assurance, et sinon aux limites de la politique d'expédition.\n\n## 6. Annulation\nVous pouvez annuler avant l'enlèvement. Les remboursements suivent la politique de remboursement.\n\n## 7. Droit applicable\nÀ compléter pour chaque marché."],
            ],
            'privacy' => [
                'en' => ['Privacy policy', 'How we collect, use and protect your personal data.', self::LEGAL_NOTE_EN."## Data we collect\nAccount details (name, email, phone), addresses, shipment contents, payment proofs and technical logs.\n\n## Why we use it\nTo price, book and deliver shipments, verify payments, prevent fraud, comply with customs and legal obligations, and answer your requests.\n\n## Legal basis\nPerformance of the contract, legal obligations, and our legitimate interest in preventing fraud. Optional cookies need your consent.\n\n## Retention\nPayment proofs: 24 months. Logs: 12 months. Audit records: 5 years. Invoices: as required by law.\n\n## Your rights\nYou can access, correct, export and ask to delete your data from your account or by writing to ".config('platform.brand.data_contact').".\n\n## Security\nProofs and documents are stored privately and shown only through short-lived links to you and authorised staff. Sensitive fields are encrypted."],
                'fr' => ['Politique de confidentialité', 'Comment nous collectons, utilisons et protégeons vos données.', self::LEGAL_NOTE_FR."## Données collectées\nInformations de compte (nom, e-mail, téléphone), adresses, contenu des envois, preuves de paiement et journaux techniques.\n\n## Finalités\nTarifer, réserver et livrer les envois, vérifier les paiements, prévenir la fraude, respecter les obligations douanières et légales, et répondre à vos demandes.\n\n## Base légale\nExécution du contrat, obligations légales et intérêt légitime de prévention de la fraude. Les cookies non essentiels nécessitent votre consentement.\n\n## Conservation\nPreuves de paiement : 24 mois. Journaux : 12 mois. Journal d'audit : 5 ans. Factures : selon la loi.\n\n## Vos droits\nAccès, rectification, export et suppression depuis votre compte ou en écrivant à ".config('platform.brand.data_contact').".\n\n## Sécurité\nLes preuves et documents sont stockés de façon privée et accessibles uniquement par des liens temporaires. Les champs sensibles sont chiffrés."],
            ],
            'cookies' => [
                'en' => ['Cookie policy', 'The cookies we use and why.', self::LEGAL_NOTE_EN."## Essential cookies\n- **Session** cookie: keeps you signed in and protects forms against forgery (CSRF).\n- **XSRF-TOKEN**: security token for requests from this site.\n- **device_id**: recognises your device to warn you about sign-ins from new devices.\n\nThese cookies are required and do not need consent.\n\n## Analytics\nWe do not use advertising or analytics cookies, and we do not send personal data to analytics providers."],
                'fr' => ['Politique cookies', 'Les cookies que nous utilisons et pourquoi.', self::LEGAL_NOTE_FR."## Cookies essentiels\n- Cookie de **session** : maintient votre connexion et protège les formulaires (CSRF).\n- **XSRF-TOKEN** : jeton de sécurité pour les requêtes du site.\n- **device_id** : reconnaît votre appareil pour vous alerter d'une connexion depuis un nouvel appareil.\n\nCes cookies sont nécessaires et ne requièrent pas de consentement.\n\n## Mesure d'audience\nNous n'utilisons ni cookies publicitaires ni cookies de mesure d'audience."],
            ],
            'shipping-policy' => [
                'en' => ['Shipping and liability policy', 'Transit times, responsibilities and limits of liability.', self::LEGAL_NOTE_EN."## Transit times\nTransit times are estimates. Customs, weather and carrier events can cause delays.\n\n## Responsibility for contents\nThe sender is responsible for the contents, their declaration and their packing.\n\n## Liability\nWithout insurance, liability for loss or damage is limited per kilogram according to applicable international conventions. With insurance, cover is up to the declared value.\n\n## Delivery\nThe last mile in the United States is handled by USPS or UPS and in Europe by FedEx. Their delivery conditions apply to that leg."],
                'fr' => ["Politique d'expédition et de responsabilité", 'Délais, responsabilités et limites.', self::LEGAL_NOTE_FR."## Délais\nLes délais sont des estimations. La douane, la météo et les transporteurs peuvent causer des retards.\n\n## Responsabilité du contenu\nL'expéditeur est responsable du contenu, de sa déclaration et de son emballage.\n\n## Responsabilité\nSans assurance, la responsabilité est limitée par kilogramme selon les conventions internationales applicables. Avec assurance, jusqu'à la valeur déclarée.\n\n## Livraison\nLe dernier kilomètre est assuré par USPS ou UPS aux États-Unis et par FedEx en Europe. Leurs conditions s'appliquent à cette étape."],
            ],
            'claims-policy' => [
                'en' => ['Claims procedure', 'How to report a lost, damaged or delayed shipment.', self::LEGAL_NOTE_EN."## Time limits\n- Damage: within 7 days of delivery\n- Loss: within 30 days of the expected delivery date\n\n## How to claim\nOpen a claim from your account (Claims page), describe what happened and upload photos of the parcel, packaging and contents.\n\n## What happens next\nWe review the evidence, may ask for more information, and send our decision by email. Approved claims are paid to the original payer."],
                'fr' => ['Procédure de réclamation', 'Signaler un envoi perdu, endommagé ou en retard.', self::LEGAL_NOTE_FR."## Délais\n- Dommage : dans les 7 jours suivant la livraison\n- Perte : dans les 30 jours suivant la date prévue\n\n## Comment réclamer\nOuvrez une réclamation depuis votre compte, décrivez les faits et joignez des photos du colis, de l'emballage et du contenu.\n\n## Ensuite\nNous examinons les éléments, pouvons demander des précisions et vous informons par e-mail. Les indemnisations sont versées au payeur d'origine."],
            ],
            'prohibited-items' => [
                'en' => ['Prohibited and restricted items', 'Goods we cannot carry, and goods that need special handling.', "## Prohibited\nCash and negotiable instruments, weapons and ammunition, explosives and fireworks, narcotics, perishable food, live animals, counterfeit goods, hazardous chemicals.\n\n## Restricted\n- **Medicine**: only with a prescription and in personal quantities\n- **Lithium batteries**: only installed in devices, never loose\n- **Electronics**: declare brand, model and value\n\nBookings with prohibited categories are blocked automatically. Undeclared prohibited items are handed to the authorities."],
                'fr' => ['Articles interdits et réglementés', 'Les biens que nous ne transportons pas et ceux soumis à conditions.', "## Interdits\nEspèces et titres négociables, armes et munitions, explosifs et feux d'artifice, stupéfiants, denrées périssables, animaux vivants, contrefaçons, produits chimiques dangereux.\n\n## Réglementés\n- **Médicaments** : sur ordonnance et en quantité personnelle\n- **Batteries lithium** : uniquement installées dans un appareil\n- **Électronique** : déclarer marque, modèle et valeur\n\nLes réservations avec des catégories interdites sont bloquées automatiquement."],
            ],
            'refund-policy' => [
                'en' => ['Refund policy', 'When and how refunds are made.', self::LEGAL_NOTE_EN."## Before pickup\nIf you cancel a paid order before pickup, we refund the amount paid, minus non-recoverable payment fees.\n\n## After pickup\nShipping charges are not refundable once the shipment is in transit, except under an approved claim.\n\n## How refunds are paid\nRefunds are recorded by our team and paid back to the original payer, using the original method where possible. Gift cards are non-refundable once redeemed."],
                'fr' => ['Politique de remboursement', 'Quand et comment nous remboursons.', self::LEGAL_NOTE_FR."## Avant l'enlèvement\nEn cas d'annulation d'une commande payée avant l'enlèvement, nous remboursons le montant payé, hors frais de paiement non récupérables.\n\n## Après l'enlèvement\nLes frais d'expédition ne sont pas remboursables une fois l'envoi en transit, sauf réclamation acceptée.\n\n## Modalités\nLes remboursements sont enregistrés par notre équipe et versés au payeur d'origine, si possible par le même moyen. Les cartes cadeaux ne sont pas remboursables une fois utilisées."],
            ],
            'payment-terms' => [
                'en' => ['Payment terms', 'How manual payments, proofs and verification work.', self::LEGAL_NOTE_EN."## How to pay\nChoose a payment method on your order. Account details are shown only to you, after you choose. Send the exact amount and write your payment reference in the note.\n\n## Proof and verification\nUpload a screenshot or receipt. A payment verifier checks the amount, date, payer and reference, and may confirm in our account statement. Orders above a set amount need two verifiers.\n\n## Release\nYour label, tracking number and invoice are released only after approval.\n\n## Gift cards\nWhere offered, gift cards are accepted as payment for shipping services only and are non-refundable once redeemed. Never buy gift cards because someone else asked you to pay for a parcel.\n\n## Fraud\nForged or altered proofs are refused and may be reported."],
                'fr' => ['Conditions de paiement', 'Fonctionnement des paiements manuels, des preuves et de la vérification.', self::LEGAL_NOTE_FR."## Payer\nChoisissez un moyen de paiement sur votre commande. Les coordonnées s'affichent uniquement pour vous, après ce choix. Envoyez le montant exact avec votre référence dans le motif.\n\n## Preuve et vérification\nEnvoyez une capture ou un reçu. Un vérificateur contrôle le montant, la date, le payeur et la référence. Au-delà d'un certain montant, deux vérificateurs sont nécessaires.\n\n## Libération\nVotre étiquette, votre numéro de suivi et votre facture sont libérés uniquement après validation.\n\n## Cartes cadeaux\nLorsqu'elles sont proposées, elles sont acceptées uniquement pour nos services et ne sont pas remboursables une fois utilisées. N'achetez jamais de cartes cadeaux à la demande d'un tiers.\n\n## Fraude\nLes preuves falsifiées sont refusées et peuvent être signalées."],
            ],
        ];
    }

    /**
     * @return array<string, array<int, array{0: string, 1: string, 2: string}>>
     */
    private function faqs(): array
    {
        return [
            'en' => [
                ['tracking', 'When do I get my tracking number?', 'As soon as our team approves your payment. You receive it by email and it appears in your account with your label.'],
                ['tracking', 'Can I track USPS, UPS and FedEx parcels here?', 'Yes. Paste the number in the tracking box: we recognise the carrier from the format. Live carrier data appears once our tracking provider is connected; otherwise we link to the carrier site.'],
                ['tracking', 'Why does my number say "not found"?', 'Check for typos. New shipments appear only after payment approval. If the problem continues, contact us with your order number.'],
                ['payments', 'Which payment methods do you accept?', 'The methods enabled for your order appear on the payment page. Account details are shown only after you choose a method, on your own order.'],
                ['payments', 'How long does payment verification take?', 'Our target is 30 minutes during staffed hours. You receive an email as soon as it is done.'],
                ['payments', 'What should my proof of payment show?', 'The amount, the date, the recipient and your payment reference. A screenshot of the confirmation screen or a PDF receipt works best.'],
                ['payments', 'Someone asked me to pay for a parcel. Is it safe?', 'Only pay for shipments you booked yourself. If a seller, a contact or a "customs officer" asks you to pay a fee or buy gift cards, it may be a scam. Contact us first.'],
                ['shipping', 'How is the price calculated?', 'From the origin and destination zones, the chargeable weight (the larger of actual and volumetric weight) and the transport mode, plus fuel and handling surcharges and optional insurance.'],
                ['shipping', 'Can I ship by road to another continent?', 'No. Road freight is offered only when origin and destination are on the same landmass. The quote tool suggests air or sea instead.'],
                ['customs', 'Who pays import duties?', 'Usually the recipient, according to the rules of the destination country. See the customs guide for details.'],
                ['account', 'How do I turn on two-factor authentication?', 'Go to Profile and security in your account and follow the steps with an authenticator app.'],
                ['account', 'How do I delete my account?', 'Use the deletion request in Profile and security. We confirm by email within 30 days.'],
            ],
            'fr' => [
                ['tracking', 'Quand vais-je recevoir mon numéro de suivi ?', 'Dès que notre équipe valide votre paiement. Vous le recevez par e-mail et il apparaît dans votre compte avec votre étiquette.'],
                ['tracking', 'Puis-je suivre des colis USPS, UPS et FedEx ici ?', 'Oui. Collez le numéro dans la zone de suivi : nous reconnaissons le transporteur. Les données en direct apparaissent une fois notre fournisseur connecté ; sinon nous renvoyons vers le site du transporteur.'],
                ['tracking', 'Pourquoi mon numéro est-il « introuvable » ?', 'Vérifiez la saisie. Les nouveaux envois apparaissent seulement après validation du paiement. Sinon, contactez-nous avec votre numéro de commande.'],
                ['payments', 'Quels moyens de paiement acceptez-vous ?', 'Les moyens activés pour votre commande s’affichent sur la page de paiement. Les coordonnées ne sont visibles qu’après votre choix, sur votre propre commande.'],
                ['payments', 'Combien de temps dure la vérification du paiement ?', 'Notre objectif est de 30 minutes pendant les heures d’ouverture. Vous recevez un e-mail dès qu’elle est terminée.'],
                ['payments', 'Que doit montrer ma preuve de paiement ?', 'Le montant, la date, le bénéficiaire et votre référence de paiement. Une capture de l’écran de confirmation ou un reçu PDF conviennent le mieux.'],
                ['payments', 'Quelqu’un me demande de payer un colis. Est-ce sûr ?', 'Ne payez que les envois que vous avez réservés vous-même. Si un vendeur, un contact ou un « douanier » vous demande des frais ou des cartes cadeaux, il peut s’agir d’une arnaque. Contactez-nous d’abord.'],
                ['shipping', 'Comment le prix est-il calculé ?', 'Selon les zones de départ et d’arrivée, le poids taxable (le plus élevé du poids réel et volumétrique) et le mode de transport, plus les surcharges carburant et manutention et l’assurance optionnelle.'],
                ['shipping', 'Puis-je envoyer par la route vers un autre continent ?', 'Non. Le transport routier n’est proposé que si le départ et l’arrivée sont sur le même continent. L’outil de devis propose alors l’aérien ou le maritime.'],
                ['customs', 'Qui paie les droits de douane ?', 'En général le destinataire, selon les règles du pays de destination. Consultez le guide des douanes.'],
                ['account', 'Comment activer la double authentification ?', 'Allez dans Profil et sécurité et suivez les étapes avec une application d’authentification.'],
                ['account', 'Comment supprimer mon compte ?', 'Utilisez la demande de suppression dans Profil et sécurité. Nous confirmons par e-mail sous 30 jours.'],
            ],
        ];
    }
}
