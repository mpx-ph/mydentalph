<?php
$staff_nav_active = 'block_schedule';
require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Schedule Management - Staff Portal</title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2b8beb",
                        "background": "#f8fafc",
                        "surface": "#ffffff",
                        "on-background": "#101922",
                        "on-surface-variant": "#404752"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Manrope", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"]
                    }
                }
            }
        };
    </script>
    <style>
        body { font-family: "Manrope", sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 450, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .mesh-bg {
            background-color: #f8fafc;
            background-image:
                radial-gradient(at 0% 0%, rgba(43, 139, 235, 0.03) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.02) 0px, transparent 50%);
        }
        .elevated-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .elevated-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px -16px rgba(15, 23, 42, 0.18);
        }
        .provider-page-enter {
            animation: provider-page-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        @keyframes provider-page-in {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .staff-input {
            border: 1px solid #e2e8f0;
            background: #ffffff;
            border-radius: 0.85rem;
            font-size: 0.85rem;
            font-weight: 700;
            color: #0f172a;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .staff-input:focus {
            outline: none;
            border-color: rgba(43, 139, 235, 0.55);
            box-shadow: 0 0 0 3px rgba(43, 139, 235, 0.14);
        }
        .staff-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.72rem 1rem;
            border-radius: 0.8rem;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            transition: all 0.2s ease;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #475569;
        }
        .staff-action-btn:hover {
            border-color: rgba(43, 139, 235, 0.35);
            color: #1d4ed8;
            background: #eff6ff;
        }
        .staff-action-btn-primary {
            border-color: #2b8beb;
            background: #2b8beb;
            color: #ffffff;
            box-shadow: 0 12px 20px -14px rgba(43, 139, 235, 0.95);
        }
        .staff-action-btn-primary:hover {
            border-color: #1f7edb;
            background: #1f7edb;
            color: #ffffff;
        }
        .timeline-row {
            display: grid;
            grid-template-columns: minmax(9rem, 12rem) 1fr;
            gap: 1rem;
            align-items: center;
            padding: 0.9rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.95rem;
            background: #ffffff;
        }
        .staff-modal-overlay {
            backdrop-filter: blur(2px);
        }
        .staff-modal-panel {
            box-shadow: 0 24px 64px -12px rgba(15, 23, 42, 0.25);
        }
        @media (max-width: 767px) {
            .timeline-row {
                grid-template-columns: 1fr;
                gap: 0.65rem;
            }
        }
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 provider-page-enter">
    <?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>

    <div class="p-10 space-y-7">
        <section class="flex flex-col gap-4">
            <div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
                <span class="w-12 h-[1.5px] bg-primary"></span> SCHEDULE MANAGEMENT
            </div>
            <div>
                <h1 class="font-headline text-5xl font-extrabold tracking-tighter leading-tight text-on-background">
                    Schedule <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Management</span>
                </h1>
                <p class="font-body text-lg font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-3">
                    Manage staff and dentist shifts and breaks.
                </p>
            </div>
        </section>

        <section class="elevated-card rounded-3xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-white flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em]">Top Controls</h2>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-slate-100 text-slate-500 border border-slate-200">
                    Placeholder UI
                </span>
            </div>
            <div class="p-6 grid grid-cols-1 lg:grid-cols-12 gap-4">
                <div class="lg:col-span-5">
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Select Staff / Dentist</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">person</span>
                        <select class="staff-input w-full py-2.5 pl-10 pr-10 appearance-none">
                            <option selected>Dr. Samantha Cruz</option>
                            <option>Dr. Adrian Santos</option>
                            <option>Staff Maria Lopez</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-base pointer-events-none">expand_more</span>
                    </div>
                </div>
                <div class="lg:col-span-3">
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Date</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">calendar_today</span>
                        <input type="date" class="staff-input w-full py-2.5 pl-10 pr-4"/>
                    </div>
                </div>
                <div class="lg:col-span-4 flex flex-wrap items-end gap-2.5">
                    <button type="button" class="staff-action-btn">
                        <span class="material-symbols-outlined text-[16px]">today</span> Today
                    </button>
                    <button type="button" data-open-modal="addBreakModal" class="staff-action-btn staff-action-btn-primary !bg-rose-500 !border-rose-500 hover:!bg-rose-600 hover:!border-rose-600">
                        <span class="material-symbols-outlined text-[16px]">free_breakfast</span> Add Break
                    </button>
                    <button type="button" data-open-modal="editShiftModal" class="staff-action-btn staff-action-btn-primary">
                        <span class="material-symbols-outlined text-[16px]">edit_calendar</span> Edit Shift
                    </button>
                    <span class="inline-flex items-center px-2.5 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-amber-100 text-amber-700 border border-amber-200">
                        Manager Only
                    </span>
                </div>
            </div>
        </section>

        <section class="elevated-card rounded-3xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-white flex items-center justify-between gap-4">
                <h3 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em]">Daily Timeline</h3>
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Static Dummy Blocks</div>
            </div>
            <div class="p-6 grid grid-cols-1 xl:grid-cols-12 gap-6">
                <div class="xl:col-span-8 space-y-3">
                    <div class="timeline-row">
                        <div class="text-sm font-extrabold text-slate-700">08:00 AM - 10:00 AM</div>
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 font-bold text-xs uppercase tracking-[0.15em]">Available Block</div>
                    </div>
                    <div class="timeline-row">
                        <div class="text-sm font-extrabold text-slate-700">10:00 AM - 11:00 AM</div>
                        <div class="rounded-xl border border-rose-200 bg-rose-50 text-rose-700 px-4 py-3 font-bold text-xs uppercase tracking-[0.15em]">Break Block</div>
                    </div>
                    <div class="timeline-row">
                        <div class="text-sm font-extrabold text-slate-700">11:00 AM - 01:00 PM</div>
                        <div class="rounded-xl border border-blue-200 bg-blue-50 text-blue-700 px-4 py-3 font-bold text-xs uppercase tracking-[0.15em]">Appointment Block</div>
                    </div>
                    <div class="timeline-row">
                        <div class="text-sm font-extrabold text-slate-700">01:00 PM - 05:00 PM</div>
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 font-bold text-xs uppercase tracking-[0.15em]">Available Block</div>
                    </div>
                </div>
                <aside class="xl:col-span-4 space-y-4">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                        <h4 class="text-xs font-black text-slate-500 uppercase tracking-[0.18em] mb-3">Color Legend</h4>
                        <div class="space-y-2.5">
                            <div class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-3 py-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                <span class="text-sm font-semibold text-slate-700">Available</span>
                            </div>
                            <div class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-3 py-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                                <span class="text-sm font-semibold text-slate-700">Break</span>
                            </div>
                            <div class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-3 py-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                                <span class="text-sm font-semibold text-slate-700">Appointment</span>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    </div>
</main>

<div id="addBreakModal" class="staff-modal-overlay fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4">
    <div class="staff-modal-panel bg-white rounded-3xl border border-slate-100 w-full max-w-xl max-h-[92vh] flex flex-col overflow-hidden">
        <div class="shrink-0 px-6 sm:px-7 pt-6 pb-4 border-b border-slate-100 flex items-start gap-4">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-rose-100 ring-1 ring-rose-200">
                <span class="material-symbols-outlined text-xl text-rose-600">free_breakfast</span>
            </div>
            <div class="min-w-0 flex-1 pr-2">
                <h3 class="text-xl font-extrabold font-headline text-on-background tracking-tight">Add Break</h3>
                <p class="text-sm text-slate-500 mt-1 leading-relaxed">UI shell only for break scheduling modal.</p>
            </div>
            <button type="button" data-close-modal="addBreakModal" class="shrink-0 p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" aria-label="Close">
                <span class="material-symbols-outlined text-[22px]">close</span>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto px-6 sm:px-7 py-6">
            <div class="h-36 rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 flex items-center justify-center text-xs font-bold uppercase tracking-widest text-slate-400">
                Break Modal Fields Placeholder
            </div>
        </div>
        <div class="shrink-0 border-t border-slate-100 bg-slate-50/60 px-6 sm:px-7 py-4 flex flex-wrap items-center justify-end gap-3">
            <button type="button" data-close-modal="addBreakModal" class="staff-action-btn !text-xs !px-4 !py-2.5">Close</button>
        </div>
    </div>
</div>

<div id="editShiftModal" class="staff-modal-overlay fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4">
    <div class="staff-modal-panel bg-white rounded-3xl border border-slate-100 w-full max-w-xl max-h-[92vh] flex flex-col overflow-hidden">
        <div class="shrink-0 px-6 sm:px-7 pt-6 pb-4 border-b border-slate-100 flex items-start gap-4">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 ring-1 ring-primary/15">
                <span class="material-symbols-outlined text-xl text-primary">edit_calendar</span>
            </div>
            <div class="min-w-0 flex-1 pr-2">
                <h3 class="text-xl font-extrabold font-headline text-on-background tracking-tight">Edit Shift</h3>
                <p class="text-sm text-slate-500 mt-1 leading-relaxed">UI shell only for shift editing modal.</p>
            </div>
            <button type="button" data-close-modal="editShiftModal" class="shrink-0 p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" aria-label="Close">
                <span class="material-symbols-outlined text-[22px]">close</span>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto px-6 sm:px-7 py-6">
            <div class="h-36 rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 flex items-center justify-center text-xs font-bold uppercase tracking-widest text-slate-400">
                Shift Modal Fields Placeholder
            </div>
        </div>
        <div class="shrink-0 border-t border-slate-100 bg-slate-50/60 px-6 sm:px-7 py-4 flex flex-wrap items-center justify-end gap-3">
            <button type="button" data-close-modal="editShiftModal" class="staff-action-btn !text-xs !px-4 !py-2.5">Close</button>
        </div>
    </div>
</div>

<script>
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    document.querySelectorAll('[data-open-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            openModal(button.getAttribute('data-open-modal'));
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            closeModal(button.getAttribute('data-close-modal'));
        });
    });

    document.querySelectorAll('[id$="Modal"]').forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal(modal.id);
            }
        });
    });
</script>
</body>
</html>
