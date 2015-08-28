<!DOCTYPE html>
<html>
<body>
@foreach($clients as $uri)
    <script>
        (function() {
            'use strict';
            var iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.addEventListener('load', function() {
                this.parentNode.removeChild(this);
                if (document.getElementsByTagName('iframe').length === 0) {
                    window.location = '{{ $redirect }}';
                }
            });
            iframe.src="{{ $uri }}?empty_return=1";
            document.body.appendChild(iframe);
        })();
    </script>
@endforeach
</body>
</html>