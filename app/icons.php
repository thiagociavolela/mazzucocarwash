<?php
function icon(string $name,string $class=''): string {
 $paths=[
 'arrow'=>'<path d="M5 12h14m-6-6 6 6-6 6"/>','chevron'=>'<path d="m9 5 7 7-7 7"/>',
 'spark'=>'<path d="m12 3 2.3 6.7L21 12l-6.7 2.3L12 21l-2.3-6.7L3 12l6.7-2.3L12 3Z"/>',
 'car'=>'<path d="m5 7 2-3h10l2 3 2 4v7H3v-7l2-4Zm0 0h14M3 12h18M6 18v3m12-3v3M6 15h2m8 0h2"/>',
 'drop'=>'<path d="M12 2S5 10 5 15a7 7 0 0 0 14 0c0-5-7-13-7-13Z"/><path d="M8 15a4 4 0 0 0 4 4"/>',
 'shield'=>'<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/>',
 'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 11h18m-13 4h2m4 0h2"/>',
 'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
 'pin'=>'<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
 'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
 'home'=>'<path d="m3 10 9-7 9 7v11H3V10Z"/><path d="M9 21v-8h6v8"/>',
 'grid'=>'<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
 'check'=>'<path d="m5 12 4 4L19 6"/>','close'=>'<path d="m6 6 12 12M6 18 18 6"/>',
 'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
 'wallet'=>'<path d="M20 7H5a2 2 0 0 1 0-4h13v4M4 7v14h17V7M21 12h-6v5h6"/>',
 'tag'=>'<path d="M3 3h8l10 10-8 8L3 11V3Z"/><circle cx="7" cy="7" r="1"/>',
 'settings'=>'<path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z"/><path d="m9 3-1 3-3 1-2 5 2 5 3 1 1 3h6l1-3 3-1 2-5-2-5-3-1-1-3H9Z"/>',
 'logout'=>'<path d="M9 3H3v18h6m5-14 5 5-5 5m-7-5h12"/>',
 'image'=>'<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="2"/><path d="m3 18 6-6 4 4 3-5 5 7"/>',
 'mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
 'phone'=>'<path d="m5 3 4 1 1 4-3 2a15 15 0 0 0 7 7l2-3 4 1 1 4c-1 5-9 1-13-3S0 4 5 3Z"/>',
 'history'=>'<path d="M3 11a9 9 0 1 1 2 7M3 4v7h7m2-4v5l3 2"/>',
 'truck'=>'<path d="M3 5h11v13H3V5Zm11 5h4l3 4v4h-7M7 18a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm11 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>',
 'lock'=>'<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4m-4 5v3"/>',
 'plus'=>'<path d="M12 4v16M4 12h16"/>',
 'edit'=>'<path d="m15 4 5 5M3 21l5-1L21 7l-5-5L3 15v6Z"/>',
 ];
 return '<svg class="icon '.e($class).'" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['spark']).'</svg>';
}
function sprites(): void { echo '<svg xmlns="http://www.w3.org/2000/svg" class="svg-sprites" aria-hidden="true"><defs>';foreach(['arrow','chevron','spark','car','drop','shield','calendar','clock','pin','user','home','grid','check','close','menu','wallet','tag','settings','logout','image','mail','phone','history','truck','lock','plus','edit'] as $name){$s=icon($name);$s=preg_replace('~^<svg[^>]*>|</svg>$~','',$s);echo '<symbol id="i-'.$name.'" viewBox="0 0 24 24">'.$s.'</symbol>';}echo '</defs></svg>'; }
