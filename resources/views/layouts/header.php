<?php

/** @var array|null $user */

if (!isset($user) || !is_array($user)) {
    $user = $_SESSION['user'] ?? null;
}

?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars(\App\Helpers\Lang::locale()) ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars(
            $_ENV['PHIXINN_PAYROLL_SYSTEM']
            ?? 'PHIXINN PAYROLL SYSTEM'
        ) ?>
    </title>

    <script src="https://cdn.tailwindcss.com"></script>


<style>
/* =========================================================
   PHIXINN PAYROLL — GLOBAL PURPLE THEME
   Shared across every page.
   ========================================================= */

:root {
    --phixinn-purple: #5b4ce2;
    --phixinn-purple-dark: #4f46d8;
    --phixinn-purple-hover: #6758e7;
    --phixinn-purple-soft: #f0edff;
}

/* Main navigation/header */
nav.bg-slate-900 {
    background: linear-gradient(
        135deg,
        var(--phixinn-purple) 0%,
        #6b5ce8 100%
    ) !important;
    border-bottom-color: rgba(255, 255, 255, .18) !important;
}

/* Active navigation */
nav .bg-slate-800 {
    background: rgba(255, 255, 255, .18) !important;
    color: #ffffff !important;
}

nav .hover\:bg-slate-800:hover {
    background: rgba(255, 255, 255, .14) !important;
}

/* Primary buttons used throughout the existing pages */
.bg-slate-900 {
    background-color: var(--phixinn-purple) !important;
}

.hover\:bg-slate-700:hover {
    background-color: var(--phixinn-purple-dark) !important;
}

/* Logout / secondary dark controls */
.bg-slate-700 {
    background-color: var(--phixinn-purple-dark) !important;
}

.hover\:bg-slate-600:hover {
    background-color: var(--phixinn-purple-hover) !important;
}

/* Common form focus state */
input:focus,
select:focus,
textarea:focus {
    border-color: #a79cf5 !important;
    box-shadow: 0 0 0 2px rgba(91, 76, 226, .16) !important;
}

/* Global purple selection */
::selection {
    background: #dcd7ff;
    color: #30258f;
}
</style>

</head>

<body class="bg-slate-100 text-slate-800">

<?php

/* --------------------------------------------------------------------------
 | Current page path
 * -------------------------------------------------------------------------- */

$currentPath = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
);


/* --------------------------------------------------------------------------
 | Available languages
 * -------------------------------------------------------------------------- */

$availableLocales = [
    'en' => 'EN',
    'tl' => 'TL',
    'zh' => '中文',
];

$currentLocale = \App\Helpers\Lang::locale();


/* --------------------------------------------------------------------------
 | Navigation styling
 * -------------------------------------------------------------------------- */

$navBase =
    'px-3 py-2 rounded-md text-sm font-medium transition-colors duration-150';

$navInactive =
    'text-slate-300 hover:bg-slate-800 hover:text-white';

$navActive =
    'bg-slate-800 text-white';


/* --------------------------------------------------------------------------
 | Safe user values
 * --------------------------------------------------------------------------
 |
 | IMPORTANT:
 | This does NOT change Auth.
 | It only prevents the layout from disappearing when $user
 | is not explicitly passed by a controller.
 * -------------------------------------------------------------------------- */

$userName = '';
$userRole = '';

if (is_array($user)) {

    $userName =
        (string) (
            $user['full_name']
            ?? ''
        );

    $userRole =
        (string) (
            $user['role']
            ?? ''
        );
}

?>

<nav class="bg-slate-900 text-white border-b border-slate-800">

    <div class="max-w-7xl mx-auto px-6">

        <div class="h-16 flex items-center justify-between">

            <!-- LEFT SIDE -->

            <div class="flex items-center gap-8 min-w-0">

                <!-- SYSTEM NAME -->

                <a
                    href="/dashboard"
                    class="shrink-0 text-sm font-bold tracking-wide text-white"
                >
                    <?= __('header.system_name') ?>
                </a>


                <!-- NAVIGATION -->

                <div class="hidden md:flex items-center gap-1">

                    <!-- DASHBOARD -->

                    <a
                        href="/dashboard"
                        class="<?= $navBase . ' ' . (
                            $currentPath === '/dashboard'
                                ? $navActive
                                : $navInactive
                        ) ?>"
                    >
                        <?= __('nav.dashboard') ?>
                    </a>


                    <!-- EMPLOYEES -->

                    <a
                        href="/employees"
                        class="<?= $navBase . ' ' . (
                            str_starts_with(
                                $currentPath,
                                '/employees'
                            )
                                ? $navActive
                                : $navInactive
                        ) ?>"
                    >
                        <?= __('nav.employees') ?>
                    </a>


                    <!-- ATTENDANCE -->

                    <a
                        href="/attendance"
                        class="<?= $navBase . ' ' . (
                            str_starts_with(
                                $currentPath,
                                '/attendance'
                            )
                                ? $navActive
                                : $navInactive
                        ) ?>"
                    >
                        <?= __('nav.attendance') ?>
                    </a>


                    <!-- SALARY CALCULATION -->

                    <a
                        href="/salary-calculation"
                        class="<?= $navBase . ' ' . (
                            str_starts_with(
                                $currentPath,
                                '/salary-calculation'
                            )
                                ? $navActive
                                : $navInactive
                        ) ?>"
                    >
                        <?= __('nav.salary_calculation') ?>
                    </a>


                    <!-- SETTINGS -->

                    <?php if ($userRole === 'admin'): ?>

                        <a
                            href="/settings"
                            class="<?= $navBase . ' ' . (
                                str_starts_with(
                                    $currentPath,
                                    '/settings'
                                )
                                    ? $navActive
                                    : $navInactive
                            ) ?>"
                        >
                            <?= __('nav.settings') ?>
                        </a>

                    <?php endif; ?>

                </div>

            </div>


            <!-- RIGHT SIDE -->

            <div class="flex items-center gap-3 shrink-0">

                <!-- LANGUAGE SWITCHER -->

                <div class="hidden sm:flex items-center gap-1">

                    <?php foreach (
                        $availableLocales
                        as $code => $label
                    ): ?>

                        <a
                            href="/lang/<?= htmlspecialchars($code) ?>"
                            class="
                                px-2.5
                                py-1.5
                                rounded-md
                                text-xs
                                font-medium
                                transition-colors
                                duration-150
                                <?= $currentLocale === $code
                                    ? 'bg-slate-700 text-white'
                                    : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                                ?>
                            "
                        >
                            <?= htmlspecialchars($label) ?>
                        </a>

                    <?php endforeach; ?>

                </div>


                <!-- USER -->

                <?php if ($userName !== ''): ?>

                    <div class="hidden sm:flex items-center">

                        <span class="text-sm text-slate-300 whitespace-nowrap">

                            <?= htmlspecialchars(
                                $userName
                            ) ?>

                            <?php if ($userRole !== ''): ?>

                                <span class="text-slate-500">

                                    (<?= htmlspecialchars(
                                        $userRole
                                    ) ?>)

                                </span>

                            <?php endif; ?>

                        </span>

                    </div>

                <?php endif; ?>


                <!-- LOGOUT -->

                <a
                    href="/logout"
                    class="
                        inline-flex
                        items-center
                        px-3
                        py-2
                        rounded-md
                        bg-slate-700
                        hover:bg-slate-600
                        text-sm
                        font-medium
                        text-white
                        transition-colors
                        duration-150
                    "
                >
                    <?= __('nav.logout') ?>
                </a>

            </div>

        </div>

    </div>

</nav>


<main class="max-w-7xl mx-auto px-6 py-8">