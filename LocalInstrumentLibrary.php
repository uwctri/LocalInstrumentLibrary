<?php

namespace UWMadison\LocalInstrumentLibrary;

use ExternalModules\AbstractExternalModule;

class LocalInstrumentLibrary extends AbstractExternalModule
{
    public function redcap_every_page_top($project_id)
    {
        if (!$this->isPage('Design/online_designer.php') || empty($project_id))
            return;

        $library_url = $this->getUrl('index.php');
?>
        <script type="text/javascript">
            $(document).ready(() => {
                let button = $('button:contains("Upload")')
                if (button.length === 0)
                    return;

                let newButton = button.clone();
                newButton.attr('id', 'local-library-btn');
                newButton.attr('href', '<?= $library_url; ?>');
                newButton.html('<i class="fas fa-book-open"></i> Local Instrument Library');
                newButton.attr('style', '')
                newButton.removeAttr('onclick');

                let newContainer = button.closest('div').clone();
                newContainer.html(newButton[0].outerHTML);

                button.closest('div').after(newContainer);
            });
            $('body').on('click', '#local-library-btn', function(e) {
                e.preventDefault();
                window.location.href = $(this).attr('href');
            });
        </script>
<?php
    }
}
