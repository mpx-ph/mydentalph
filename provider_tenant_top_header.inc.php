<?php
declare(strict_types=1);

$__clinic_title = htmlspecialchars((string) ($clinic_display_name ?? 'My Clinic'), ENT_QUOTES, 'UTF-8');
$__dn = trim((string) ($display_name ?? ''));
$__header_name = htmlspecialchars($__dn !== '' ? $__dn : 'Signed in', ENT_QUOTES, 'UTF-8');
$__header_email = htmlspecialchars(trim((string) ($user_email_display ?? '')), ENT_QUOTES, 'UTF-8');
$__email_hidden = ($user_email_display ?? '') === '' || trim((string) $user_email_display) === '';
$__avatar = htmlspecialchars((string) ($avatar_initials ?? 'MD'), ENT_QUOTES, 'UTF-8');
?>
<header class="provider-top-header fixed top-0 right-0 left-64 z-30 min-h-[4.5rem] sm:h-20 sm:min-h-0 bg-white/90 backdrop-blur-xl border-b border-slate-200/60 shadow-sm shadow-slate-200/30" data-purpose="top-header">
<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pl-20 pr-4 sm:px-6 lg:px-10 py-3 sm:py-0 sm:h-full">
<div class="min-w-0 flex-1">
<p class="text-[10px] font-bold uppercase tracking-[0.2em] text-primary/80 mb-0.5">Clinic</p>
<h1 class="text-lg sm:text-xl font-extrabold font-headline text-on-background truncate tracking-tight"><?php echo $__clinic_title; ?></h1>
</div>
<div class="flex items-center gap-2 sm:gap-3 shrink-0">
<button type="button" class="hover:bg-surface-container-low rounded-full p-2.5 transition-all relative border-0 bg-transparent cursor-pointer hidden sm:inline-flex" aria-label="Notifications">
<span class="material-symbols-outlined text-on-surface-variant">notifications</span>
</button>
<button type="button" class="hover:bg-surface-container-low rounded-full p-2.5 transition-all border-0 bg-transparent cursor-pointer hidden sm:inline-flex" aria-label="Help">
<span class="material-symbols-outlined text-on-surface-variant">help_outline</span>
</button>
<button type="button" id="open-profile-modal" class="group flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-white/80 pl-1 pr-2.5 py-1 shadow-sm text-left cursor-pointer hover:border-primary/35 hover:bg-white hover:shadow-md transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:ring-offset-2" aria-label="Account settings" aria-haspopup="dialog" aria-expanded="false" aria-controls="profile-account-modal">
<div id="header-account-avatar" class="w-10 h-10 rounded-xl bg-primary/15 flex items-center justify-center text-primary text-xs font-bold border border-primary/10 shrink-0 group-hover:bg-primary/20 transition-colors" aria-hidden="true"><?php echo $__avatar; ?></div>
<div class="min-w-0 text-left">
<p id="header-account-name" class="text-xs font-bold text-on-background truncate max-w-[10rem] sm:max-w-[14rem] group-hover:text-primary transition-colors"><?php echo $__header_name; ?></p>
<p id="header-account-email" class="text-[11px] leading-tight text-on-surface-variant truncate max-w-[10rem] sm:max-w-[14rem]<?php echo $__email_hidden ? ' opacity-0 pointer-events-none select-none' : ''; ?>"<?php echo $__email_hidden ? ' aria-hidden="true"' : ''; ?>><?php echo $__email_hidden ? "\u{00A0}" : $__header_email; ?></p>
</div>
</button>
</div>
</div>
</header>
