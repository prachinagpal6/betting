var gemini = require('gemini');

gemini.suite('yandex-search', function(suite) {
    suite.setUrl('/')
    .setCaptureElements('.home-logo')
    .capture('plain');
});