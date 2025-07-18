/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';

// Import Bootstrap CSS
import 'bootstrap/dist/css/bootstrap.min.css';

// Import Bootstrap JS
import 'bootstrap';

// Import Stimulus
import { Application } from '@hotwired/stimulus';
import { definitionsFromContext } from '@hotwired/stimulus-webpack-helpers';

// Create Stimulus application
const application = Application.start();

// Import and register all controllers from the controllers directory
const context = require.context('./controllers', true, /\.js$/);
application.load(definitionsFromContext(context));
