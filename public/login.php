<?php
require_once("../include/bittorrent.php");
dbconn();

$langid = intval($_GET['sitelanguage'] ?? 0);
if ($langid)
{
    $lang_folder = validlang($langid);
    $enabled = \App\Models\Language::listEnabled();
    if (!in_array($lang_folder, $enabled)) {
        nexus_redirect(getBaseUrl());
    }
    if(get_langfolder_cookie() != $lang_folder)
    {
        set_langfolder_cookie($lang_folder);
        nexus_redirect($_SERVER['REQUEST_URI']);
    }
}
require_once(get_langfile_path("", false, $CURLANGDIR));

failedloginscheck ();
cur_user_check ();
stdhead($lang_login['head_login']);
?>    <style>
        /* Override default styles and apply custom theme */
        body {
            margin: 0 !important;
            padding: 0 !important;
            font-family: 'Segoe UI', 'Arial', sans-serif !important;
            background-color: #0f1a2b !important;
            color: #fff !important;
            overflow-x: hidden !important;
            background-image: url('') !important; /* 请确保此图片存在 */
            background-size: cover !important;
            background-position: center !important;
            background-attachment: fixed !important;
        }
        
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, rgba(15,26,43,0.7), rgba(42,20,72,0.7));
            z-index: -1;
        }
        
        .star {
            position: absolute;
            background-color: #fff;
            border-radius: 50%;
            animation: twinkle 4s infinite ease-in-out;
        }
        
        @keyframes twinkle {
            0%, 100% { opacity: 0.2; }
            50% { opacity: 1; }
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            position: relative;
            z-index: 1;
        }
        
        .title-container {
            text-align: center;
            margin-bottom: 40px;
            padding-top: 40px;
        }
        
        .main-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(to right, #ff3d85, #7c4dff, #00c9ff);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: 0 0 10px rgba(255,61,133,0.3), 0 0 20px rgba(124,77,255,0.2);
            letter-spacing: 2px;
        }
        
        .subtitle {
            font-size: 1.2rem;
            color: #80cbc4;
            margin-top: 5px;
        }
        
        .login-box {
            background-color: rgba(21, 32, 53, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #80cbc4;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: #fff;
            transition: all 0.3s;
            outline: none;
        }
        
        .form-control:focus {
            border-color: #7c4dff;
            box-shadow: 0 0 0 3px rgba(124, 77, 255, 0.2);
        }
        
        .row-head {
            color: #80cbc4;
            padding: 10px 0;
        }
        
        .btn {
            background: linear-gradient(to right, #ff3d85, #7c4dff);
            border: none;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn:hover {
            background: linear-gradient(to right, #ff5a9e, #9673ff);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(124, 77, 255, 0.4);
        }
        
        .btn-reset {
            background: rgba(255, 255, 255, 0.1);
            color: #ddd;
            margin-left: 10px;
        }
        
        .btn-reset:hover {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: none;
        }
        
        .btn-oauth {
            background: linear-gradient(to right, #4CAF50, #45a049);
            margin: 5px;
            padding: 8px 16px;
            font-size: 0.9rem;
        }
        
        .btn-oauth:hover {
            background: linear-gradient(to right, #5cbf60, #4caf50);
            transform: translateY(-1px);
        }
        
        /* Override table styles */
        table {
            width: 100%;
            border: none !important;
            background: transparent !important;
        }
        
        td {
            background: transparent !important;
            border: none !important;
        }
        
        /* Override form styles */
        input[type="text"], input[type="password"] {
            background-color: rgba(255, 255, 255, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            color: #fff !important;
        }
        
        input[type="text"]:focus, input[type="password"]:focus {
            border-color: #7c4dff !important;
            box-shadow: 0 0 0 3px rgba(124, 77, 255, 0.2) !important;
        }
        
        /* Override link styles */
        a {
            color: #80cbc4 !important;
            text-decoration: none !important;
        }
        
        a:hover {
            color: #ff3d85 !important;
            text-shadow: 0 0 8px rgba(255, 61, 133, 0.5) !important;
        }
        
        .checkbox-container {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .checkbox-container input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .language-selector {
            text-align: right;
            margin-bottom: 20px;
        }
        
        select {
            background-color: rgba(21, 32, 53, 0.8);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 8px;
            border-radius: 8px;
        }
        
        .links {
            margin-top: 20px;
            text-align: center;
        }
        
        .links a {
            color: #80cbc4;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .links a:hover {
            color: #ff3d85;
            text-shadow: 0 0 8px rgba(255, 61, 133, 0.5);
        }
        
        .advanced-options {
            margin-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
        }
        
        .advanced-title {
            color: #80cbc4;
            margin-bottom: 15px;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .oauth-section {
            margin: 20px 0;
            padding: 20px;
            background-color: rgba(76, 175, 80, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(76, 175, 80, 0.3);
            text-align: center;
        }
        
        .oauth-title {
            color: #4CAF50;
            margin-bottom: 15px;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .help-box {
            margin-top: 40px;
        }
        
        .help-box-title {
            color: #80cbc4;
            margin-bottom: 15px;
            font-size: 1.3rem;
            font-weight: 600;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 10px;
        }
        
        iframe {
            background-color: rgba(21, 32, 53, 0.5);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .flex-input {
            display: flex;
            margin-top: 10px;
        }
        
        .flex-input input[type="text"] {
            flex-grow: 1;
            margin-right: 10px;
        }
        
        .welcome-text {
            text-align: center;
            color: #ddd;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .login-box {
                padding: 20px;
            }
            
            .main-title {
                font-size: 2.5rem;
            }
        }
    

        /* ======================================================================
           Codex Login UI Refresh 2026-08-15
           Purpose: align login page with the NovaHD white/teal glass UI.
           ====================================================================== */
        :root {
            --nova-login-deep: #102a35;
            --nova-login-deeper: #071923;
            --nova-login-green: #0f766e;
            --nova-login-mint: #10b981;
            --nova-login-blue: #2563eb;
            --nova-login-ink: #0f172a;
            --nova-login-muted: #5f7480;
            --nova-login-shadow: 0 28px 72px rgba(15, 35, 45, .18);
        }

        body {
            color: var(--nova-login-ink) !important;
            font-family: "Inter", "Segoe UI", "Microsoft YaHei", Arial, sans-serif !important;
            background:
                radial-gradient(circle at 12% 12%, rgba(16,185,129,.20), transparent 30%),
                radial-gradient(circle at 86% 8%, rgba(37,99,235,.16), transparent 34%),
                linear-gradient(135deg, #e8fbf5 0%, #f7fbff 46%, #e6f2ff 100%) !important;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: -2;
            background-image:
                linear-gradient(rgba(255,255,255,.56) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.56) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: linear-gradient(to bottom, rgba(0,0,0,.72), transparent 80%);
        }

        .overlay {
            position: fixed !important;
            inset: 0 !important;
            z-index: -1 !important;
            pointer-events: none !important;
            background:
                linear-gradient(90deg, rgba(16,185,129,.14), transparent 24%, transparent 76%, rgba(37,99,235,.13)),
                radial-gradient(circle at 50% 18%, rgba(255,255,255,.78), transparent 44%) !important;
        }

        .star {
            display: none !important;
        }

        table.head {
            background: rgba(255,255,255,.70) !important;
            border: 1px solid rgba(255,255,255,.84) !important;
            box-shadow: 0 18px 48px rgba(15,35,45,.10) !important;
            backdrop-filter: blur(16px) !important;
            -webkit-backdrop-filter: blur(16px) !important;
        }

        table.mainouter {
            background: rgba(255,255,255,.48) !important;
            border: 1px solid rgba(255,255,255,.80) !important;
            box-shadow: var(--nova-login-shadow) !important;
            backdrop-filter: blur(18px) !important;
            -webkit-backdrop-filter: blur(18px) !important;
            padding: 0 !important;
            overflow: hidden !important;
        }

        table.mainouter > tbody > tr > td {
            padding: 0 !important;
            background: transparent !important;
        }

        #nav_block {
            padding: 18px 18px 0 !important;
            background: transparent !important;
        }

        #nav_block > a {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            min-width: 82px !important;
            height: 34px !important;
            margin: 0 4px !important;
            border-radius: 999px !important;
            color: #0f766e !important;
            background: rgba(255,255,255,.72) !important;
            border: 1px solid rgba(15,118,110,.16) !important;
            text-decoration: none !important;
            box-shadow: 0 8px 22px rgba(15,35,45,.08) !important;
        }

        #nav_block font,
        #nav_block b {
            color: inherit !important;
            font-size: 14px !important;
            letter-spacing: .12em !important;
        }

        #nav_block > a:first-child {
            color: #fff !important;
            background: linear-gradient(135deg, #102a35, #0f766e) !important;
            box-shadow: 0 12px 30px rgba(15,118,110,.22) !important;
        }

        .container {
            width: min(1120px, calc(100% - 42px)) !important;
            max-width: none !important;
            margin: 0 auto !important;
            padding: 42px 0 58px !important;
            position: relative !important;
            z-index: 1 !important;
        }

        .title-container {
            text-align: center !important;
            margin: 0 auto 24px !important;
            padding: 14px 18px 18px !important;
        }

        .title-container::before {
            content: "";
            display: block;
            width: 44px;
            height: 4px;
            margin: 0 auto 18px;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--nova-login-green), var(--nova-login-blue));
        }

        .main-title {
            margin: 0 !important;
            color: var(--nova-login-ink) !important;
            background: none !important;
            -webkit-text-fill-color: var(--nova-login-ink) !important;
            font-size: 58px !important;
            line-height: 1 !important;
            font-weight: 900 !important;
            letter-spacing: .02em !important;
            text-shadow: 0 1px 0 rgba(255,255,255,.75), 0 16px 38px rgba(15,35,45,.13) !important;
        }

        .subtitle {
            margin: 14px 0 0 !important;
            color: #0f766e !important;
            font-size: 17px !important;
            font-weight: 650 !important;
            letter-spacing: .06em !important;
        }

        .language-selector {
            width: min(760px, 100%) !important;
            margin: 0 auto 14px !important;
            text-align: right !important;
            color: #45616d !important;
            font-size: 12px !important;
        }

        .language-selector select,
        select {
            min-height: 34px !important;
            padding: 4px 34px 4px 12px !important;
            border-radius: 999px !important;
            border: 1px solid rgba(15,118,110,.18) !important;
            color: #102a35 !important;
            background: rgba(255,255,255,.88) !important;
            box-shadow: 0 8px 18px rgba(15,35,45,.08) !important;
            outline: none !important;
        }

        .login-box {
            position: relative !important;
            width: min(760px, 100%) !important;
            margin: 0 auto !important;
            padding: 34px 42px 36px !important;
            box-sizing: border-box !important;
            color: rgba(255,255,255,.92) !important;
            background:
                radial-gradient(circle at 88% 10%, rgba(16,185,129,.25), transparent 28%),
                linear-gradient(145deg, rgba(16,42,53,.98), rgba(8,25,35,.96)) !important;
            border: 1px solid rgba(255,255,255,.16) !important;
            border-radius: 22px !important;
            box-shadow: 0 26px 58px rgba(15,35,45,.25), inset 0 1px 0 rgba(255,255,255,.08) !important;
            overflow: hidden !important;
            backdrop-filter: blur(14px) !important;
            -webkit-backdrop-filter: blur(14px) !important;
        }

        .login-box::before {
            content: "\767B\5F55 NOVAHD";
            display: inline-flex;
            align-items: center;
            height: 30px;
            padding: 0 13px;
            margin-bottom: 18px;
            border-radius: 999px;
            color: rgba(255,255,255,.88);
            background: rgba(255,255,255,.09);
            border: 1px solid rgba(255,255,255,.12);
            font-size: 12px;
            font-weight: 850;
            letter-spacing: .14em;
        }

        .login-box table {
            width: 100% !important;
            border: 0 !important;
            background: transparent !important;
            border-collapse: separate !important;
            border-spacing: 0 10px !important;
        }

        .login-box td {
            border: 0 !important;
            background: transparent !important;
            vertical-align: middle !important;
        }

        .row-head {
            width: 132px !important;
            padding: 0 18px 0 0 !important;
            color: #9af3e2 !important;
            font-size: 13px !important;
            font-weight: 800 !important;
            letter-spacing: .03em !important;
            text-align: left !important;
            white-space: nowrap !important;
        }

        .login-box input[type="text"],
        .login-box input[type="password"],
        .login-box input.password,
        .login-box .form-control {
            width: 100% !important;
            min-height: 42px !important;
            box-sizing: border-box !important;
            padding: 0 14px !important;
            border-radius: 12px !important;
            border: 1px solid rgba(255,255,255,.16) !important;
            color: #102a35 !important;
            background: rgba(244,250,252,.96) !important;
            box-shadow: inset 0 1px 1px rgba(15,35,45,.06) !important;
            outline: none !important;
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease, transform .18s ease !important;
        }

        .login-box input[type="text"]::placeholder,
        .login-box input[type="password"]::placeholder {
            color: #7b8c95 !important;
        }

        .login-box input[type="text"]:focus,
        .login-box input[type="password"]:focus,
        .login-box input.password:focus,
        .login-box .form-control:focus {
            background: #fff !important;
            border-color: rgba(16,185,129,.82) !important;
            box-shadow: 0 0 0 4px rgba(16,185,129,.18), inset 0 1px 1px rgba(15,35,45,.04) !important;
            transform: translateY(-1px) !important;
        }

        .welcome-text {
            margin: 0 0 10px !important;
            color: rgba(226,244,247,.82) !important;
            text-align: center !important;
            font-size: 13px !important;
            line-height: 1.7 !important;
        }

        .welcome-text b {
            color: #34d399 !important;
        }

        .advanced-title {
            padding: 18px 0 4px !important;
            color: #8cf0df !important;
            font-size: 16px !important;
            font-weight: 900 !important;
            letter-spacing: .05em !important;
            border-top: 1px solid rgba(255,255,255,.10) !important;
        }

        .checkbox-container {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            color: rgba(226,244,247,.78) !important;
            font-size: 13px !important;
        }

        .checkbox-container input[type="checkbox"] {
            width: 16px !important;
            height: 16px !important;
            accent-color: var(--nova-login-mint) !important;
        }

        .btn,
        input.btn,
        input[type="button"].btn,
        input[type="submit"].btn,
        input[type="reset"].btn {
            min-width: 88px !important;
            height: 42px !important;
            padding: 0 20px !important;
            border: 0 !important;
            border-radius: 999px !important;
            color: #fff !important;
            background: linear-gradient(135deg, #0f766e, #10b981) !important;
            box-shadow: 0 14px 28px rgba(16,185,129,.24) !important;
            cursor: pointer !important;
            font-size: 14px !important;
            font-weight: 900 !important;
            letter-spacing: .08em !important;
            text-decoration: none !important;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease !important;
        }

        .btn:hover,
        input.btn:hover {
            color: #fff !important;
            background: linear-gradient(135deg, #10b981, #2563eb) !important;
            box-shadow: 0 18px 34px rgba(37,99,235,.24) !important;
            transform: translateY(-1px) !important;
            text-shadow: none !important;
        }

        .btn-reset,
        input.btn-reset,
        input[type="reset"].btn-reset {
            color: #d7f8f2 !important;
            background: rgba(255,255,255,.12) !important;
            border: 1px solid rgba(255,255,255,.15) !important;
            box-shadow: none !important;
            margin-left: 10px !important;
        }

        .btn-reset:hover,
        input.btn-reset:hover {
            background: rgba(255,255,255,.20) !important;
        }

        .links {
            width: min(760px, 100%) !important;
            margin: 24px auto 0 !important;
            padding: 22px 24px !important;
            box-sizing: border-box !important;
            text-align: center !important;
            color: #3c5662 !important;
            background: rgba(255,255,255,.66) !important;
            border: 1px solid rgba(255,255,255,.80) !important;
            border-radius: 20px !important;
            box-shadow: 0 16px 40px rgba(15,35,45,.10) !important;
        }

        .links p {
            margin: 8px 0 !important;
            line-height: 1.7 !important;
        }

        a,
        a:link,
        a:visited {
            color: #0f766e !important;
            text-decoration: none !important;
            font-weight: 750 !important;
            text-shadow: none !important;
        }

        a:hover {
            color: #2563eb !important;
            text-shadow: none !important;
        }

        .oauth-section {
            width: min(760px, 100%) !important;
            margin: 20px auto 0 !important;
            padding: 18px 22px !important;
            box-sizing: border-box !important;
            border-radius: 18px !important;
            text-align: center !important;
            background: rgba(255,255,255,.64) !important;
            border: 1px solid rgba(15,118,110,.13) !important;
            box-shadow: 0 14px 34px rgba(15,35,45,.08) !important;
        }

        .oauth-title {
            margin-bottom: 12px !important;
            color: #102a35 !important;
            font-size: 15px !important;
            font-weight: 900 !important;
        }

        .btn-oauth {
            min-width: auto !important;
            height: 36px !important;
            margin: 5px !important;
            padding: 0 16px !important;
            font-size: 13px !important;
        }

        .help-box {
            margin-top: 24px !important;
        }

        .help-box-title {
            margin: 0 0 14px !important;
            padding: 0 0 12px !important;
            color: #102a35 !important;
            border-bottom: 1px solid rgba(15,118,110,.12) !important;
            font-size: 18px !important;
            font-weight: 900 !important;
        }

        .help-box table,
        .help-box td {
            background: transparent !important;
            border: 0 !important;
        }

        iframe {
            background: rgba(255,255,255,.74) !important;
            border: 1px solid rgba(15,118,110,.12) !important;
            border-radius: 14px !important;
            box-shadow: inset 0 1px 1px rgba(15,35,45,.04) !important;
        }

        .flex-input {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            margin-top: 10px !important;
        }

        .flex-input input[type="text"] {
            flex: 1 1 auto !important;
            min-width: 0 !important;
            margin-right: 0 !important;
        }

        @media (max-width: 820px) {
            .container {
                width: calc(100% - 22px) !important;
                padding: 28px 0 38px !important;
            }

            .main-title {
                font-size: 42px !important;
            }

            .login-box {
                padding: 24px 18px 26px !important;
                border-radius: 18px !important;
            }

            .login-box table,
            .login-box tbody,
            .login-box tr,
            .login-box td {
                display: block !important;
                width: 100% !important;
            }

            .row-head {
                padding: 0 0 7px !important;
            }

            .login-box td[align="right"] {
                text-align: center !important;
            }

            .language-selector {
                text-align: center !important;
            }

            .flex-input {
                flex-wrap: wrap !important;
            }
        }
        /* ================= End Codex Login UI Refresh ================= */



        /* ======================================================================
           Codex Login Center Layout 2026-08-15
           Purpose: remove the login artwork and center the login interface.
           ====================================================================== */
        body > table.head {
            display: none !important;
        }

        body > table.mainouter {
            margin-top: 18px !important;
        }

        .container {
            width: min(820px, calc(100% - 42px)) !important;
            max-width: 820px !important;
            display: block !important;
            margin: 0 auto !important;
            padding: 46px 0 58px !important;
        }

        .title-container,
        .language-selector,
        .login-box,
        .oauth-section,
        .links {
            width: min(760px, 100%) !important;
            max-width: 760px !important;
            margin-left: auto !important;
            margin-right: auto !important;
        }

        .title-container {
            margin-bottom: 18px !important;
        }

        .language-selector {
            text-align: right !important;
            margin-bottom: 14px !important;
        }

        .login-box {
            margin-top: 0 !important;
        }

        .nova-login-art {
            display: none !important;
        }

        @media (max-width: 820px) {
            .container {
                width: calc(100% - 22px) !important;
                padding: 28px 0 38px !important;
            }

            .language-selector {
                text-align: center !important;
            }
        }
        /* ================= End Codex Login Center Layout ================= */


        /* ======================================================================
           Codex Login Remove Blue Lines 2026-08-15
           Purpose: hide decorative blue short lines in the login title area.
           ====================================================================== */
        .title-container::before,
        .title-container::after,
        .main-title::before,
        .main-title::after,
        .subtitle::before,
        .subtitle::after {
            display: none !important;
            content: none !important;
            width: 0 !important;
            height: 0 !important;
            background: transparent !important;
            border: 0 !important;
        }
        /* ================= End Codex Login Remove Blue Lines ================= */
</style>
    <div class="overlay"></div>
    <script>
        // 创建星星背景效果
        document.addEventListener('DOMContentLoaded', function() {
            const starCount = 150;
            const body = document.body;
            
            for (let i = 0; i < starCount; i++) {
                const star = document.createElement('div');
                star.className = 'star';
                
                // 随机位置和大小
                const size = Math.random() * 2;
                star.style.width = `${size}px`;
                star.style.height = `${size}px`;
                star.style.left = `${Math.random() * 100}%`;
                star.style.top = `${Math.random() * 100}%`;
                star.style.animationDelay = `${Math.random() * 4}s`;
                
                body.appendChild(star);
            }
        });
    </script>

    <div class="container">
        <div class="title-container">
            <h1 class="main-title">NovaHD</h1>
            <p class="subtitle">欢迎回来，请登录您的账户</p>
        </div>
<?php
$s = "<select name=\"sitelanguage\" onchange='submit()'>\n";
$secret = htmlspecialchars($_GET['secret'] ?? '');
$langs = langlist("site_lang", true);
foreach ($langs as $row)
{
    if ($row["site_lang_folder"] == get_langfolder_cookie()) $se = "selected=\"selected\""; else $se = "";
    $s .= "<option value=\"". $row["id"] ."\" ". $se. ">" . htmlspecialchars($row["lang_name"]) . "</option>\n";
}
$s .= "\n</select>";
?>
        <form method="get" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
            <input type="hidden" name="secret" value="<?php echo $secret ?>">
<?php
print("<div class=\"language-selector\">".$lang_login['text_select_lang']. $s . "</div>");
?>
        </form>
        <div class="login-box">
<?php

unset($returnto);
if (!empty($_GET["returnto"])) {
    $returnto = $_GET["returnto"];
    if (empty($_GET["nowarn"])) {
        print("<h1>" . $lang_login['h1_not_logged_in']. "</h1>\n");
        print("<p><b>" . $lang_login['p_error']. "</b> " . $lang_login['p_after_logged_in']. "</p>\n");
    }
}
$useChallengeResponseAuthentication = \App\Models\Setting::getIsUseChallengeResponseAuthentication();
$passwordName = 'class="password"';
if (!$useChallengeResponseAuthentication) {
    $passwordName .= ' name="password"';
}
?>
            <form id="login-form" method="post" action="takelogin.php">
                <input type="hidden" name="secret" value="<?php echo $secret?>">
                <p class="welcome-text"><?php echo $lang_login['p_need_cookies_enables']?><br /> [<b><?php echo $maxloginattempts;?></b>] <?php echo $lang_login['p_fail_ban']?></p>
                <p class="welcome-text"><?php echo $lang_login['p_you_have']?> <b><?php echo remaining ();?></b> <?php echo $lang_login['p_remaining_tries']?></p>
                <table border="0" cellpadding="5">
                    <tr>
                        <td class="row-head"><?php echo $lang_login['rowhead_username']?></td>
                        <td align="left"><input type="text" class="username" name="username" autocomplete="username" style="width: 100%; padding: 12px; background-color: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; color: #fff; transition: all 0.3s; outline: none;" /></td>
                    </tr>
                    <tr>
                        <td class="row-head"><?php echo $lang_login['rowhead_password']?></td>
                        <td align="left"><input type="password" <?php echo $passwordName ?> autocomplete="current-password" style="width: 100%; padding: 12px; background-color: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; color: #fff; transition: all 0.3s; outline: none;"/></td>
                    </tr>
                    <tr>
                        <td class="row-head"><?php echo $lang_login['rowhead_two_step_code']?></td>
                        <td align="left"><input type="text" name="two_step_code" inputmode="numeric" pattern="[0-9]*" placeholder="<?php echo $lang_login['two_step_code_tooltip'] ?>" style="width: 100%; padding: 12px; background-color: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; color: #fff; transition: all 0.3s; outline: none;"/></td>
                    </tr>
<?php
show_image_code ();
if ($securelogin == "yes")
    $sec = "checked=\"checked\" disabled=\"disabled\"";
elseif ($securelogin == "no")
    $sec = "disabled=\"disabled\"";
elseif ($securelogin == "op")
    $sec = "";

if ($securetracker == "yes")
    $sectra = "checked=\"checked\" disabled=\"disabled\"";
elseif ($securetracker == "no")
    $sectra = "disabled=\"disabled\"";
elseif ($securetracker == "op")
    $sectra = "";
?>
                    <tr>
                        <td colspan="2" class="advanced-title"><?php echo $lang_login['text_advanced_options']?></td>
                    </tr>
                    <tr>
                        <td class="row-head"><?php echo $lang_login['text_auto_logout']?></td>
                        <td align="left" class="checkbox-container"><input type="checkbox" name="logout" value="yes" /><?php echo $lang_login['checkbox_auto_logout']?></td>
                    </tr>
                    <!--<tr><td class="row-head"><?php //echo $lang_login['text_restrict_ip']?></td><td class="checkbox-container" align="left"><input type="checkbox" name="securelogin" value="yes" /><?php //echo $lang_login['checkbox_restrict_ip']?></td></tr>-->
                    <!--<tr><td class="row-head"><?php //echo $lang_login['text_ssl']?></td><td class="checkbox-container" align="left">
                            <input type="checkbox" name="ssl" value="yes" <?php //echo $sec?> /><?php //echo $lang_login['checkbox_ssl']?><br />
                            <input type="checkbox" name="trackerssl" value="yes" <?php //echo $sectra?> /><?php //echo $lang_login['checkbox_ssl_tracker']?>
                        </td>
                    </tr>-->
                    <tr>
                        <td colspan="2" align="right">
                            <input id="submit-btn" type="button" value="<?php echo $lang_login['button_login']?>" class="btn" />
                            <input type="reset" value="<?php echo $lang_login['button_reset']?>" class="btn btn-reset" />
                        </td>
                    </tr>
                </table>
<?php

if (isset($returnto)) {
    print("<input type=\"hidden\" name=\"returnto\" value=\"" . htmlspecialchars($returnto) . "\" />\n");
}
if ($useChallengeResponseAuthentication) {
    print('<input type="hidden" name="response" />');
}
\App\Repositories\UserPasskeyRepository::renderLogin();
?>
            </form>
        </div>
        
<?php
$oauthProviders = \App\Models\OauthProvider::query()
    ->orderBy("priority", 'desc')
    ->where('enabled', '=', 1)
    ->get();
$items = [];
foreach ($oauthProviders as $oauthProvider) {
    $items[] = sprintf('<a href="oauth/redirect/%s" class="btn btn-oauth">%s</a>', $oauthProvider->uuid, $oauthProvider->name);
}
if (!empty($items)) {
    echo '<div class="oauth-section">';
    echo sprintf('<div class="oauth-title">%s</div>', $lang_login['other_methods']);
    echo implode("", $items);
    echo '</div>';
}
?>
        
        <div class="links">
<?php
if (\App\Models\Setting::getIsComplainEnabled()) {
    echo sprintf('<p>[<b><a href="complains.php">%s</a></b>]</p>', $lang_login['text_complain']);
}
?>
            <p><?php echo $lang_login['p_no_account_signup']?></p>
<?php
if ($smtptype != 'none'){
?>
            <p><?php echo $lang_login['p_forget_pass_recover']?></p>
            <p><?php echo $lang_login['p_account_banned']?></p>
            <p><?php echo $lang_login['p_resend_confirm']?></p>
<?php
}
if ($showhelpbox_main != 'no'){?>
            <div class="help-box">
                <h2 class="help-box-title"><?php echo $lang_login['text_helpbox'] ?><font class="small"> - <?php echo $lang_login['text_helpbox_note'] ?><font id= "waittime" color="red"></font></h2>
<?php
print("<table width='100%' border='1' cellspacing='0' cellpadding='1'><tr><td class=\"text\">\n");
print("<iframe src='" . get_protocol_prefix() . $BASEURL . "/shoutbox.php?type=helpbox' width='100%' height='180' frameborder='0' name='sbox' marginwidth='0' marginheight='0'></iframe><br /><br />\n");
print("<form action='" . get_protocol_prefix() . $BASEURL . "/shoutbox.php' id='helpbox' method='get' target='sbox' name='shbox'>\n");
print("<div class=\"flex-input\">" . $lang_login['text_message']."<input type='text' id=\"hbtext\" name='shbox_text' autocomplete='off' class='form-control' ><input type='submit' id='hbsubmit' class='btn' name='shout' value=\"".$lang_login['sumbit_shout']."\" /><input type='reset' class='btn btn-reset' value=".$lang_login['submit_clear']." /> <input type='hidden' name='sent' value='yes'><input type='hidden' name='type' value='helpbox' /></div>\n");
print("<div id=sbword style=\"display: none\">".$lang_login['sumbit_shout']."</div>");
print(smile_row("shbox","shbox_text"));
print("</td></tr></table></form></td></tr></table>");
}
?>
        </div>
    </div>
<?php
render_password_challenge_js("login-form", "username", "password");
\Nexus\Nexus::js('js/passkey.js', 'footer', true);
stdfoot();
