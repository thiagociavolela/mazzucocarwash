<aside id="pwa-install" class="pwa-install" role="region" aria-label="Instalar aplicativo Mazzuco" hidden>
 <span class="brand-emblem"><?=icon('car')?></span>
 <div class="pwa-install-copy"><strong>Seu cuidado, a um toque.</strong><p>Instale o app Mazzuco na sua tela inicial.</p></div>
 <button type="button" class="btn btn-primary btn-sm" id="pwa-install-button">Instalar app <?=icon('arrow')?></button>
 <button type="button" class="icon-button pwa-dismiss" id="pwa-dismiss" aria-label="Lembrar em outro momento"><?=icon('close')?></button>
</aside>
<dialog id="pwa-help" class="pwa-help" aria-labelledby="pwa-help-title">
 <div class="pwa-help-heading"><span class="brand-emblem"><?=icon('car')?></span><button type="button" class="icon-button" id="pwa-help-close" aria-label="Fechar instruções"><?=icon('close')?></button></div>
 <h2 id="pwa-help-title">Leve a Mazzuco com você.</h2>
 <p id="pwa-help-description"></p>
 <ol id="pwa-help-steps"></ol>
 <p class="pwa-help-note">O aplicativo usa a internet para acessar sua conta, consultar horários e realizar pagamentos.</p>
 <button type="button" class="btn btn-primary form-submit" id="pwa-help-done">Entendi <?=icon('check')?></button>
</dialog>
