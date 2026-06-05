<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('copy-url', (event) => {
            const url = event[0]?.url || event.url;
            if (url) {
                navigator.clipboard.writeText(url).then(() => {
                    console.log('URL copied to clipboard:', url);
                }).catch(err => {
                    console.error('Failed to copy URL:', err);
                });
            }
        });
    });
</script>

