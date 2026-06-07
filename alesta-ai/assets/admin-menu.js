/**
 * Alesta AI — open the "Passer à Pro" sidebar link in a new tab.
 * The link points to alesta-ai.com/tarifs.html (external) and is rendered as a
 * regular sidebar item by WordPress; we add target="_blank" + rel here.
 */
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var links = document.querySelectorAll(
            '#adminmenu a[href*="alesta-ai.com/tarifs"]'
        );
        for (var i = 0; i < links.length; i++) {
            links[i].setAttribute('target', '_blank');
            links[i].setAttribute('rel', 'noopener noreferrer');
        }
    });
}());
