<?php

declare( strict_types=1 );

namespace HWS\BaseTools\MaintenanceMode;

defined( 'ABSPATH' ) || exit;

final class MaintenanceTemplateRenderer {
    public static function document( mixed $template ): string {
        $template = MaintenanceSettings::normalize_template( $template );
        $site_name = self::site_name();
        $site_url = function_exists( 'home_url' ) ? home_url( '/' ) : '/';
        $content = self::template_content( $template, $site_name, $site_url );

        return '<!doctype html>' . "\n"
            . '<html lang="en">' . "\n"
            . '<head>' . "\n"
            . '  <meta charset="utf-8">' . "\n"
            . '  <meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '  <meta name="robots" content="noindex,nofollow,noarchive">' . "\n"
            . '  <title>Maintenance — ' . esc_html( $site_name ) . '</title>' . "\n"
            . '  <style>' . "\n" . self::base_css() . "\n" . $content['css'] . "\n  </style>\n"
            . '</head>' . "\n"
            . '<body class="hws-maintenance hws-maintenance--' . esc_attr( $template ) . '">' . "\n"
            . $content['html'] . "\n"
            . '</body>' . "\n"
            . '</html>';
    }

    /** @return array{css:string,html:string} */
    private static function template_content( string $template, string $site_name, string $site_url ): array {
        $name = esc_html( $site_name );
        $url = esc_url( $site_url );

        if ( 'editorial' === $template ) {
            return [
                'css' => <<<'CSS'
    body{background:#efece5;color:#171713}.frame{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(300px,.85fr);min-height:100vh}.story{display:flex;flex-direction:column;justify-content:space-between;padding:clamp(32px,7vw,96px)}.brand{font-size:13px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}.issue{align-items:center;display:flex;gap:12px}.issue:before{background:#171713;content:"";height:1px;width:42px}.story h1{font-family:Georgia,"Times New Roman",serif;font-size:clamp(58px,8vw,126px);font-weight:400;letter-spacing:-.065em;line-height:.82;margin:48px 0 36px;max-width:780px}.story p{font-family:Georgia,"Times New Roman",serif;font-size:clamp(18px,2vw,25px);line-height:1.55;margin:0;max-width:620px}.aside{background:#b6d4c8;display:flex;flex-direction:column;justify-content:center;padding:clamp(32px,6vw,84px);position:relative}.aside:before{border:1px solid rgba(23,23,19,.28);content:"";inset:24px;pointer-events:none;position:absolute}.number{font-family:Georgia,"Times New Roman",serif;font-size:clamp(90px,15vw,210px);letter-spacing:-.09em;line-height:.75}.aside h2{font-size:14px;letter-spacing:.16em;margin:48px 0 12px;text-transform:uppercase}.aside p{font-size:16px;line-height:1.6;margin:0;max-width:360px}@media(max-width:760px){.frame{grid-template-columns:1fr}.story{min-height:62vh}.aside{min-height:38vh}.story h1{font-size:clamp(54px,17vw,88px)}}
CSS,
                'html' => '  <main class="frame">' . "\n"
                    . '    <section class="story">' . "\n"
                    . '      <div class="brand">' . $name . '</div>' . "\n"
                    . '      <div><div class="issue">A brief intermission</div><h1>Good things<br>are taking<br>shape.</h1><p>We are refining the site and preparing the next edition. Please check back shortly.</p></div>' . "\n"
                    . '    </section>' . "\n"
                    . '    <aside class="aside"><div class="number">01</div><h2>Current status</h2><p>Scheduled maintenance is in progress. The website will return as soon as the final checks are complete.</p></aside>' . "\n"
                    . '  </main>',
            ];
        }

        if ( 'blueprint' === $template ) {
            return [
                'css' => <<<'CSS'
    body{background-color:#0b3f78;background-image:linear-gradient(rgba(255,255,255,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.06) 1px,transparent 1px);background-size:32px 32px;color:#f4f8ff}.shell{display:flex;flex-direction:column;justify-content:center;margin:auto;min-height:100vh;padding:clamp(24px,5vw,72px);width:min(100%,1120px)}.top{align-items:center;border-bottom:1px solid rgba(255,255,255,.48);display:flex;justify-content:space-between;padding-bottom:16px}.brand{font-size:13px;font-weight:800;letter-spacing:.15em;text-transform:uppercase}.live{align-items:center;display:flex;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;gap:9px}.dot{animation:pulse 1.8s infinite;background:#7bf0b2;border-radius:50%;height:8px;width:8px}.grid{display:grid;gap:clamp(30px,6vw,84px);grid-template-columns:1.2fr .8fr;padding:clamp(48px,8vw,100px) 0}.eyebrow{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;letter-spacing:.12em;text-transform:uppercase}.copy h1{font-size:clamp(52px,8vw,112px);letter-spacing:-.065em;line-height:.88;margin:24px 0}.copy p{font-size:clamp(17px,2vw,22px);line-height:1.6;margin:0;max-width:670px}.panel{align-self:center;background:rgba(2,26,54,.36);border:1px solid rgba(255,255,255,.45);padding:24px}.panel h2{font-size:12px;letter-spacing:.13em;margin:0 0 20px;text-transform:uppercase}.row{align-items:center;border-top:1px solid rgba(255,255,255,.22);display:flex;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px;justify-content:space-between;padding:16px 0}.ok{color:#7bf0b2}.work{color:#ffe398}.foot{border-top:1px solid rgba(255,255,255,.48);font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11px;letter-spacing:.08em;padding-top:16px;text-transform:uppercase}@keyframes pulse{50%{box-shadow:0 0 0 8px rgba(123,240,178,0);opacity:.55}}@media(max-width:760px){.grid{grid-template-columns:1fr}.copy h1{font-size:clamp(50px,15vw,82px)}}
CSS,
                'html' => '  <main class="shell">' . "\n"
                    . '    <header class="top"><div class="brand">' . $name . '</div><div class="live"><span class="dot"></span>MAINTENANCE ACTIVE</div></header>' . "\n"
                    . '    <section class="grid"><div class="copy"><div class="eyebrow">System update / in progress</div><h1>Building<br>something<br>better.</h1><p>Our site is temporarily offline while improvements are applied. Service will resume after validation is complete.</p></div>' . "\n"
                    . '      <div class="panel"><h2>Deployment checklist</h2><div class="row"><span>Core services</span><strong class="ok">ONLINE</strong></div><div class="row"><span>Website</span><strong class="work">UPDATING</strong></div><div class="row"><span>Final checks</span><strong class="work">PENDING</strong></div></div></section>' . "\n"
                    . '    <footer class="foot">Status page · ' . $url . '</footer>' . "\n"
                    . '  </main>',
            ];
        }

        if ( 'aurora' === $template ) {
            return [
                'css' => <<<'CSS'
    body{background:#080b20;color:#f7f7ff;overflow:hidden;position:relative}.glow{border-radius:50%;filter:blur(24px);opacity:.8;position:fixed}.glow.one{background:#6c5cff;height:55vw;left:-18vw;top:-24vw;width:55vw}.glow.two{background:#00d9b8;bottom:-27vw;height:55vw;right:-18vw;width:55vw}.glow.three{background:#ff4b9f;height:30vw;right:18vw;top:-16vw;width:30vw}.shell{align-items:center;display:flex;justify-content:center;min-height:100vh;padding:24px;position:relative}.card{backdrop-filter:blur(24px);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.22);border-radius:32px;box-shadow:0 40px 100px rgba(0,0,0,.38);overflow:hidden;padding:clamp(34px,7vw,88px);position:relative;width:min(100%,960px)}.card:after{background:linear-gradient(90deg,#8679ff,#2ce5c4,#ff6cae);content:"";height:4px;inset:0 0 auto;position:absolute}.brand{font-size:12px;font-weight:800;letter-spacing:.16em;text-transform:uppercase}.orb{align-items:center;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:50%;display:flex;height:74px;justify-content:center;margin:60px 0 34px;width:74px}.orb span{animation:orbit 2.8s linear infinite;border:3px solid rgba(255,255,255,.18);border-radius:50%;border-top-color:#fff;height:36px;width:36px}.card h1{font-size:clamp(48px,8vw,92px);letter-spacing:-.06em;line-height:.94;margin:0;max-width:800px}.card p{color:#d9ddf3;font-size:clamp(17px,2vw,21px);line-height:1.65;margin:28px 0 0;max-width:650px}.status{align-items:center;display:flex;font-size:13px;gap:10px;margin-top:48px}.status i{background:#46efbd;border-radius:50%;box-shadow:0 0 20px #46efbd;height:8px;width:8px}@keyframes orbit{to{transform:rotate(360deg)}}
CSS,
                'html' => '  <div class="glow one"></div><div class="glow two"></div><div class="glow three"></div>' . "\n"
                    . '  <main class="shell"><section class="card">' . "\n"
                    . '    <div class="brand">' . $name . '</div><div class="orb"><span></span></div>' . "\n"
                    . '    <h1>A brighter experience is on its way.</h1><p>We have stepped away for a moment to improve the site. Everything will be back online soon.</p>' . "\n"
                    . '    <div class="status"><i></i> Improvements are being applied</div>' . "\n"
                    . '  </section></main>',
            ];
        }

        if ( 'minimal' === $template ) {
            return [
                'css' => <<<'CSS'
    body{background:#fff;color:#0b0b0b}.shell{display:flex;flex-direction:column;justify-content:space-between;margin:auto;min-height:100vh;padding:clamp(24px,4vw,58px);width:min(100%,1500px)}.header{align-items:center;border-bottom:2px solid #0b0b0b;display:flex;font-size:12px;font-weight:800;justify-content:space-between;letter-spacing:.12em;padding-bottom:16px;text-transform:uppercase}.content{padding:clamp(54px,9vw,130px) 0}.content h1{font-size:clamp(68px,14vw,205px);font-weight:900;letter-spacing:-.085em;line-height:.73;margin:0;text-transform:uppercase}.content p{font-size:clamp(18px,2vw,26px);line-height:1.45;margin:clamp(40px,7vw,90px) 0 0;max-width:650px}.footer{align-items:flex-end;border-top:2px solid #0b0b0b;display:flex;gap:30px;justify-content:space-between;padding-top:16px}.footer strong{font-size:13px;letter-spacing:.1em;text-transform:uppercase}.mark{background:#0b0b0b;height:24px;width:24px}@media(max-width:560px){.header span:last-child{display:none}.content h1{font-size:clamp(64px,22vw,108px)}.footer{align-items:flex-start;flex-direction:column}}
CSS,
                'html' => '  <main class="shell">' . "\n"
                    . '    <header class="header"><span>' . $name . '</span><span>Temporary notice / Maintenance</span></header>' . "\n"
                    . '    <section class="content"><h1>Hold<br>that<br>thought.</h1><p>We are making a few important improvements. The site will be available again shortly.</p></section>' . "\n"
                    . '    <footer class="footer"><strong>Back soon. Thanks for your patience.</strong><span class="mark"></span></footer>' . "\n"
                    . '  </main>',
            ];
        }

        return [
            'css' => <<<'CSS'
    body{background:#f5f7fb;color:#172033}.shell{align-items:center;display:flex;justify-content:center;min-height:100vh;padding:24px}.card{background:#fff;border:1px solid #dfe4ed;border-radius:28px;box-shadow:0 28px 80px rgba(22,34,55,.12);padding:clamp(36px,7vw,78px);text-align:center;width:min(100%,820px)}.brand{color:#61708a;font-size:12px;font-weight:800;letter-spacing:.16em;text-transform:uppercase}.icon{align-items:center;background:#edf2ff;border-radius:22px;display:flex;height:80px;justify-content:center;margin:44px auto 34px;position:relative;width:80px}.icon:before,.icon:after{border:3px solid #4055df;border-radius:50%;content:"";position:absolute}.icon:before{height:34px;width:34px}.icon:after{border-left-color:transparent;border-right-color:transparent;height:52px;opacity:.42;width:52px}.card h1{font-size:clamp(44px,7vw,76px);letter-spacing:-.055em;line-height:1;margin:0}.card p{color:#5e6c84;font-size:clamp(17px,2vw,20px);line-height:1.65;margin:25px auto 0;max-width:590px}.progress{background:#e7ebf2;border-radius:99px;height:6px;margin:44px auto 16px;overflow:hidden;width:min(100%,420px)}.progress span{animation:move 2.4s ease-in-out infinite;background:#4055df;border-radius:inherit;display:block;height:100%;width:42%}.note{color:#7a879c;font-size:12px;font-weight:700;letter-spacing:.09em;text-transform:uppercase}@keyframes move{0%{transform:translateX(-105%)}100%{transform:translateX(345%)}}
CSS,
            'html' => '  <main class="shell"><section class="card">' . "\n"
                . '    <div class="brand">' . $name . '</div><div class="icon"></div>' . "\n"
                . '    <h1>We’ll be right back.</h1><p>We are performing scheduled improvements to make your experience even better. Thank you for your patience.</p>' . "\n"
                . '    <div class="progress"><span></span></div><div class="note">Maintenance in progress</div>' . "\n"
                . '  </section></main>',
        ];
    }

    private static function base_css(): string {
        return <<<'CSS'
    :root{color-scheme:light}*{box-sizing:border-box}html,body{margin:0;min-height:100%;width:100%}body{-webkit-font-smoothing:antialiased;font-family:Inter,ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}a{color:inherit}
CSS;
    }

    private static function site_name(): string {
        $name = function_exists( 'get_bloginfo' ) ? trim( (string) get_bloginfo( 'name' ) ) : '';
        return '' !== $name ? $name : 'Website';
    }
}
