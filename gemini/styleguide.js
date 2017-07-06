var gemini = require('gemini');

gemini.suite('style-guide', function(suite) {
    suite.setUrl('/style-guide.html')
        .setCaptureElements(['.site-header', '.site-branding'])
        .capture('plain');
});