<script>

(function () {

    const TEXT_ATTR = 'blogFontSize';

    const BLOCK_ATTR = 'blogBlockFontSize';



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



        if (Trix.config.blockAttributes.default) {

            Trix.config.blockAttributes.default.tagName = 'p';

        }



        Trix.config.textAttributes[TEXT_ATTR] = {

            styleProperty: 'fontSize',

            inheritable: true,

            parser: function (element) {

                return element.style.fontSize || null;

            },

        };



        Trix.config.blockAttributes[BLOCK_ATTR] = {

            tagName: 'p',

            styleProperty: 'fontSize',

            parse: function (element) {

                const tag = element.tagName;



                return (tag === 'P' || tag === 'DIV') && Boolean(element.style.fontSize);

            },

        };



        window.__blogTrixFontSize = {

            textAttr: TEXT_ATTR,

            blockAttr: BLOCK_ATTR,

            defaultPt: 12,

            minPt: 6,

            maxPt: 96,

        };

    }



    window.__blogRegisterTrixFontConfig = registerTrixConfig;



    document.addEventListener('trix-before-initialize', registerTrixConfig);



    if (typeof Trix !== 'undefined') {

        registerTrixConfig();

    }

})();

</script>

