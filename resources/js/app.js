import Alpine from '@alpinejs/csp';
import addressBook from './components/addressBook';
import countdown from './components/countdown';
import faqSearch from './components/faqSearch';
import globe from './components/globe';
import heroScenes from './components/heroScenes';
import networkPanel from './components/networkPanel';
import payPage from './components/payPage';
import placeField from './components/placeField';
import quoteForm from './components/quoteForm';
import siteHeader from './components/siteHeader';
import trackBox from './components/trackBox';
import trackPage from './components/trackPage';
import wizard from './components/wizard';
import { initReveal } from './lib/reveal';

// The CSP build of Alpine never evaluates strings as code, so the site runs without 'unsafe-eval'.
document.documentElement.classList.add('js');

Alpine.data('siteHeader', siteHeader);
Alpine.data('trackBox', trackBox);
Alpine.data('heroScenes', heroScenes);
Alpine.data('globe', globe);
Alpine.data('trackPage', trackPage);
Alpine.data('placeField', placeField);
Alpine.data('quoteForm', quoteForm);
Alpine.data('networkPanel', networkPanel);
Alpine.data('faqSearch', faqSearch);
Alpine.data('countdown', countdown);
Alpine.data('wizard', wizard);
Alpine.data('payPage', payPage);
Alpine.data('addressBook', addressBook);

Alpine.start();
initReveal();
