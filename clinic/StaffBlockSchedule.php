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
            transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s ease;
        }
        .elevated-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.12);
        }
        .provider-page-enter {
            animation: provider-page-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        @keyframes provider-page-in {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .schedule-input {
            border: none;
            background: #f8fafc;
            border-radius: 0.9rem;
            font-size: 0.86rem;
            font-weight: 700;
            color: #0f172a;
            transition: box-shadow 0.25s ease, background-color 0.25s ease;
        }
        .schedule-input:focus {
            outline: none;
            background: #f1f5f9;
            box-shadow: 0 0 0 2px rgba(43, 139, 235, 0.18);
        }
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 provider-page-enter">
    <?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>

    <div class="p-10 space-y-8">
        <section class="flex flex-col gap-4">
            <div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
                <span class="w-12 h-[1.5px] bg-primary"></span> SCHEDULE MANAGEMENT
            </div>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="font-headline text-5xl font-extrabold tracking-tighter leading-tight text-on-background">
                        Schedule <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Management</span>
                    </h1>
                    <p class="font-body text-lg font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-3">
                        Manage staff and dentist shifts and breaks.
                    </p>
                </div>
            </div>
        </section>

        <section class="elevated-card p-7 rounded-3xl">
            <div class="flex items-center justify-between gap-4 mb-5">
                <h2 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em]">Top Controls</h2>
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Placeholder UI Only</div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <div class="lg:col-span-4">
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Select Staff / Dentist</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">person</span>
                        <select class="schedule-input w-full py-3 pl-10 pr-10 appearance-none">
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
                        <input type="date" class="schedule-input w-full py-3 pl-10 pr-4"/>
                    </div>
                </div>
                <div class="lg:col-span-5 flex flex-wrap items-end gap-3">
                    <button type="button" class="px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-700 font-bold text-xs uppercase tracking-widest hover:border-primary/30 hover:text-primary transition-colors">
                        Today
                    </button>
                    <button type="button" data-open-modal="addBreakModal" class="px-5 py-3 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs uppercase tracking-widest transition-colors shadow-sm">
                        Add Break
                    </button>
                    <button type="button" data-open-modal="editShiftModal" class="px-5 py-3 rounded-xl bg-primary/90 hover:bg-primary text-white font-bold text-xs uppercase tracking-widest transition-colors shadow-sm">
                        Edit Shift
                    </button>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-amber-100 text-amber-700 border border-amber-200">
                        Manager Only
                    </span>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-4 gap-6 items-start">
            <div class="xl:col-span-3 elevated-card rounded-3xl p-7">
                <div class="flex items-center justify-between gap-4 mb-6">
                    <h3 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em]">Daily Timeline</h3>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Static Dummy Blocks</div>
                </div>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-3 md:gap-4 items-center">
                        <div class="md:col-span-2 text-sm font-bold text-slate-700">08:00 AM - 10:00 AM</div>
                        <div class="md:col-span-4 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 font-bold text-xs uppercase tracking-[0.15em]">
                            Available Block
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-3 md:gap-4 items-center">
                        <div class="md:col-span-2 text-sm font-bold text-slate-700">10:00 AM - 11:00 AM</div>
                        <div class="md:col-span-4 rounded-xl border border-rose-200 bg-rose-50 text-rose-700 px-4 py-3 font-bold text-xs uppercase tracking-[0.15em]">
                            Break Block
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-3 md:gap-4 items-center">
                        <div class="md:col-span-2 text-sm font-bold text-slate-700">11:00 AM - 01:00 PM</div>
                        <div class="md:col-span-4 rounded-xl border border-blue-200 bg-blue-50 text-blue-700 px-4 py-3 font-bold text-xs uppercase tracking-[0.15em]">
                            Appointment Block
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-3 md:gap-4 items-center">
                        <div class="md:col-span-2 text-sm font-bold text-slate-700">01:00 PM - 05:00 PM</div>
                        <div class="md:col-span-4 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 font-bold text-xs uppercase tracking-[0.15em]">
                            Available Block
                        </div>
                    </div>
                </div>
            </div>

            <aside class="elevated-card rounded-3xl p-6">
                <h3 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em] mb-5">Color Legend</h3>
                <div class="space-y-3">
                    <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2.5">
                        <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                        <span class="text-sm font-semibold text-slate-700">Available</span>
                    </div>
                    <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2.5">
                        <span class="w-3 h-3 rounded-full bg-rose-500"></span>
                        <span class="text-sm font-semibold text-slate-700">Break</span>
                    </div>
                    <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2.5">
                        <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                        <span class="text-sm font-semibold text-slate-700">Appointment</span>
                    </div>
                </div>
            </aside>
        </section>
    </div>
</main>

<div id="addBreakModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/45">
    <div class="w-full max-w-lg bg-white rounded-3xl border border-slate-200 shadow-2xl">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-headline text-xl font-extrabold text-slate-900">Add Break</h3>
            <button type="button" data-close-modal="addBreakModal" class="w-9 h-9 inline-flex items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:text-slate-700">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <p class="text-sm text-slate-500">Break form fields will be connected in the backend phase.</p>
            <div class="h-28 rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 flex items-center justify-center text-xs font-bold uppercase tracking-widest text-slate-400">
                Placeholder Content
            </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button type="button" data-close-modal="addBreakModal" class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs uppercase tracking-wider">Close</button>
        </div>
    </div>
</div>

<div id="editShiftModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/45">
    <div class="w-full max-w-lg bg-white rounded-3xl border border-slate-200 shadow-2xl">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-headline text-xl font-extrabold text-slate-900">Edit Shift</h3>
            <button type="button" data-close-modal="editShiftModal" class="w-9 h-9 inline-flex items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:text-slate-700">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <p class="text-sm text-slate-500">Shift editing controls will be connected in the backend phase.</p>
            <div class="h-28 rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 flex items-center justify-center text-xs font-bold uppercase tracking-widest text-slate-400">
                Placeholder Content
            </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button type="button" data-close-modal="editShiftModal" class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs uppercase tracking-wider">Close</button>
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
