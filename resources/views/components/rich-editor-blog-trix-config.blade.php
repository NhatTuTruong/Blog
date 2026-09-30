<script>
(function () {
    function registerTrixConfig() {
        if (typeof Trix === 'undefined') {
            return;
        }

        Trix.config.blockAttributes.heading1 = {
            tagName: 'h1',
            terminal: true,
            breakOnReturn: true,
            group: false,
        };
    }

    document.addEventListener('trix-before-initialize', registerTrixConfig);

    if (typeof Trix !== 'undefined') {
        registerTrixConfig();
    }
})();
</script>
