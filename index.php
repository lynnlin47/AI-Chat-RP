<?php
session_start();

 $user_profile = [];
if (file_exists('user_profile/user_dna.json')) {
    $user_profile = json_decode(file_get_contents('user_profile/user_dna.json'), true);
}
 $api_key = $user_profile['api_key'] ?? '';

 $characters = [];
 $json_files = glob('characters/*.json');
foreach ($json_files as $file) {
    $data = json_decode(file_get_contents($file), true);
    if ($data && isset($data['name'])) {
        $characters[] = [
            'file_name' => basename($file, '.json'),
            'name' => $data['dna']['nickname'] ?? $data['name'],
            'data' => $data
        ];
    }
}
 $has_characters = !empty($characters);

 $groups_file = 'data/groups.json';
 $groups = [];
if (file_exists($groups_file)) {
    $groups = json_decode(file_get_contents($groups_file), true) ?? [];
}

if (!is_dir('identity')) {
    mkdir('identity', 0777, true);
}

function getGroupAvatarCollage($members_data) {
    if (empty($members_data)) return '';
    $html = '<div class="w-12 h-12 rounded-full overflow-hidden border border-emerald-200 dark:border-emerald-800 grid grid-cols-2 gap-0.5 bg-slate-100 dark:bg-slate-800 flex-shrink-0">';
    $i = 0;
    foreach ($members_data as $m) {
        if ($i >= 4) break;
        $av = str_replace('\\', '/', $m['data']['avatar'] ?? '');
        $ini = mb_substr($m['data']['name'], 0, 1, 'UTF-8');
        if (!empty($av)) {
            $html .= '<img src="'.htmlspecialchars($av).'" class="w-full h-full object-cover">';
        } else {
            $html .= '<div class="w-full h-full flex items-center justify-center bg-slate-200 dark:bg-slate-700 text-slate-500 text-[10px] font-bold">'.htmlspecialchars($ini).'</div>';
        }
        $i++;
    }
    while ($i < 4) {
        $html .= '<div class="w-full h-full bg-slate-200 dark:bg-slate-700"></div>';
        $i++;
    }
    $html .= '</div>';
    return $html;
}

if (isset($_GET['select'])) {
    $target_file = $_GET['select'];
    foreach ($characters as $char) {
        if ($char['file_name'] === $target_file) {
            $_SESSION['character'] = $char['data'];
            $_SESSION['active_group_id'] = null;
            $_SESSION['chat_history'] = [];
            
            $log_filename = 'data/' . preg_replace('/[^\p{L}\p{M}\p{N}_-]/u', '_', $char['data']['name']) . '.json';
            if (file_exists($log_filename)) {
                $historical_logs = json_decode(file_get_contents($log_filename), true);
                if (is_array($historical_logs)) {
                    foreach ($historical_logs as $log) {
                        $_SESSION['chat_history'][] = ['role' => 'user', 'parts' => [['text' => $log['user_input'] ?? '']]];
                        $_SESSION['chat_history'][] = ['role' => 'model', 'parts' => [['text' => $log['ai_response'] ?? '']]];
                    }
                }
            }
            header('Location: index.php');
            exit;
        }
    }
}

if (isset($_GET['select_group'])) {
    $group_id = $_GET['select_group'];
    foreach ($groups as $grp) {
        if ($grp['id'] === $group_id) {
            $_SESSION['active_group_id'] = $group_id;
            $_SESSION['character'] = null;
            $_SESSION['chat_history'] = [];
            
            $log_filename = 'data/group_' . $group_id . '.json';
            if (file_exists($log_filename)) {
                $historical_logs = json_decode(file_get_contents($log_filename), true);
                if (is_array($historical_logs)) {
                    foreach ($historical_logs as $log) {
                        $_SESSION['chat_history'][] = ['role' => 'user', 'parts' => [['text' => $log['user_input'] ?? '']]];
                        $_SESSION['chat_history'][] = ['role' => 'model', 'parts' => [['text' => $log['ai_response'] ?? '']]];
                    }
                }
            }
            break;
        }
    }
    header('Location: index.php');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'create_group' && isset($_GET['chars'])) {
    $selected_chars = $_GET['chars'];
    sort($selected_chars);
    $group_id = md5(implode('-', $selected_chars) . uniqid());
    
    $member_names = [];
    foreach ($characters as $c) {
        if (in_array($c['file_name'], $selected_chars)) $member_names[] = $c['name'];
    }
    $new_group = [
        'id' => $group_id,
        'name' => 'กลุ่มใหม่ (' . implode(', ', $member_names) . ')',
        'avatar' => '',
        'members' => $selected_chars
    ];
    $groups[] = $new_group;
    file_put_contents($groups_file, json_encode($groups, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    $_SESSION['active_group_id'] = $group_id;
    $_SESSION['character'] = null;
    $_SESSION['chat_history'] = [];
    
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_group') {
    $group_id = $_POST['group_id'];
    $new_name = trim($_POST['group_name']);
    $new_avatar = str_replace('\\', '/', trim($_POST['group_avatar']));
    $new_members = $_POST['members'] ?? [];
    
    if (!empty($new_name) && count($new_members) >= 2) {
        foreach ($groups as &$grp) {
            if ($grp['id'] === $group_id) {
                $grp['name'] = $new_name;
                $grp['avatar'] = $new_avatar;
                $grp['members'] = $new_members;
                break;
            }
        }
        file_put_contents($groups_file, json_encode($groups, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_group') {
    $group_id = $_POST['group_id'];
    $groups = array_values(array_filter($groups, function($g) use ($group_id) {
        return $g['id'] !== $group_id;
    }));
    file_put_contents($groups_file, json_encode($groups, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    $log_filename = 'data/group_' . $group_id . '.json';
    if (file_exists($log_filename)) unlink($log_filename);
    
    if (isset($_SESSION['active_group_id']) && $_SESSION['active_group_id'] === $group_id) {
        $_SESSION['active_group_id'] = null;
        $_SESSION['chat_history'] = [];
    }
    header('Location: index.php');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    $_SESSION['chat_history'] = [];
    if (!empty($_SESSION['active_group_id'])) {
        $log_filename = 'data/group_' . $_SESSION['active_group_id'] . '.json';
    } elseif (isset($_SESSION['character'])) {
        $log_filename = 'data/' . preg_replace('/[^\p{L}\p{M}\p{N}_-]/u', '_', $_SESSION['character']['name']) . '.json';
    }
    if (isset($log_filename) && file_exists($log_filename)) unlink($log_filename);
    header('Location: index.php');
    exit;
}

if ($has_characters && !isset($_SESSION['character']) && empty($_SESSION['active_group_id'])) {
    $_SESSION['character'] = $characters[0]['data'];
}
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

 $current_group_data = null;
 $current_group_members_data = [];
if (!empty($_SESSION['active_group_id'])) {
    foreach ($groups as $grp) {
        if ($grp['id'] === $_SESSION['active_group_id']) {
            $current_group_data = $grp;
            foreach ($characters as $c) {
                if (in_array($c['file_name'], $grp['members'])) {
                    $current_group_members_data[] = $c;
                }
            }
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_chat'])) {
    header('Content-Type: application/json');
    $user_text = trim($_POST['user_message'] ?? '');
    $force_rp = $_POST['force_rp'] ?? '';
    $is_auto_talk = isset($_POST['auto_talk']) && $_POST['auto_talk'] === '1';
    $is_event_mode = isset($_POST['event_mode']) && $_POST['event_mode'] === '1';
    $response_data = ['success' => false, 'text' => '', 'error' => '', 'identity' => null];

    if (empty($api_key)) {
        $response_data['error'] = 'กรุณาตั้งค่า API Key ก่อน';
    } elseif (($is_auto_talk || !empty($user_text)) && (isset($_SESSION['character']) || !empty($current_group_data))) {
        
        $is_group_mode = !empty($current_group_data);
        $prompt_text = "";
        $log_text = "";
        
        if ($is_auto_talk) {
            if ($is_event_mode && !empty($user_text)) {
                if ($is_group_mode) {
                    $prompt_text = "[ระบบกำหนดเหตุการณ์: {$user_text}] จงดำเนินเรื่องและสนทนากันเองตามเหตุการณ์นี้ 1 รอบ โดยไม่ต้องรอให้ผู้ใช้พูด";
                } else {
                    $prompt_text = "[ระบบกำหนดเหตุการณ์: {$user_text}] จงดำเนินเรื่องและตอบสนองตามเหตุการณ์นี้ 1 รอบ โดยไม่ต้องรอให้ผู้ใช้พูด";
                }
                $log_text = "เหตุการณ์: " . $user_text;
            } else {
                if ($is_group_mode) {
                    $prompt_text = "[ระบบแจ้งเตือน: ให้ตัวละครในกลุ่มสนทนากันเองต่อไป 1 รอบ โดยไม่ต้องรอให้ผู้ใช้พูด]";
                } else {
                    $prompt_text = "[ระบบแจ้งเตือน: จงพูดอะไรสักอย่างเพื่อเริ่มหรือดำเนินเรื่องต่อ 1 รอบ โดยไม่ต้องรอให้ผู้ใช้พูด]";
                }
                $log_text = "AI คุยเอง";
            }
            $_SESSION['chat_history'][] = ['role' => 'user', 'parts' => [['text' => $prompt_text]]];
        } else {
            $has_attachment = isset($_FILES['attachment']) && $_FILES['attachment']['error'] === 0;
            $final_user_text = $user_text;
            if ($has_attachment) {
                $final_user_text .= "\n[ผู้ใช้แนบไฟล์: " . $_FILES['attachment']['name'] . "]";
            }
            $_SESSION['chat_history'][] = ['role' => 'user', 'parts' => [['text' => $final_user_text]]];
            $log_text = $final_user_text;
        }
        
        $user_has_dna = !empty($user_profile['dna']['nickname']);
        $relation_system_instruction = $user_has_dna 
            ? "สถานะความสัมพันธ์: 'คนสนิทกันมากที่สุด'" 
            : "สถานะความสัมพันธ์: 'คนเพิ่งรู้จักกัน' สนทนารักษาระยะห่าง สุภาพ";

        $identity_filename = $is_group_mode 
            ? 'identity/group_' . $current_group_data['id'] . '.json' 
            : 'identity/' . preg_replace('/[^\p{L}\p{M}\p{N}_-]/u', '_', $_SESSION['character']['name']) . '.json';
        
        $current_identity = file_exists($identity_filename) ? json_decode(file_get_contents($identity_filename), true) : null;

        $system_instruction = "คุณคือมนุษย์ที่มีชีวิตและประวัติตามรหัสพันธุกรรม (DNA) ห้ามหลุดสวมบทบาทหรือเปิดเผยว่าตัวเองคือ AI:\n";
        
        if ($is_group_mode) {
            $system_instruction .= "=== ข้อมูลตัวละครในกลุ่ม ===\n";
            $allowed_names = [];
            foreach ($current_group_members_data as $index => $g_char) {
                $g_dna = $g_char['data']['dna'];
                $system_instruction .= "[ตัวละคร " . ($index+1) . "]\n- ชื่อเล่น: {$g_dna['nickname']}\n- เพศ: {$g_dna['gender']}\n- ประวัติ: {$g_char['data']['backstory']}\n";
                $allowed_names[] = $g_dna['nickname'];
            }
            if (!empty($force_rp)) {
                $forced_name = '';
                foreach ($current_group_members_data as $g_char) {
                    if ($g_char['file_name'] === $force_rp) {
                        $forced_name = $g_char['data']['dna']['nickname'];
                        break;
                    }
                }
                if (!empty($forced_name)) {
                    $system_instruction .= "=== กฎการสนทนาในกลุ่ม ===\nในรอบนี้ ให้ตอบกลับเฉพาะตัวละครที่ชื่อ '{$forced_name}' เท่านั้น และใช้รูปแบบ '{$forced_name}: [ข้อความ]'\n";
                } else {
                    $system_instruction .= "=== กฎการสนทนาในกลุ่ม ===\nตัวละครทุกตัวต้องพูด แยกบรรทัดกันชัดเจน เช่น 'ชื่อA: [ข้อความ]\nชื่อB: [ข้อความ]'\n";
                }
            } else {
                $system_instruction .= "=== กฎการสนทนาในกลุ่ม ===\nตัวละครทุกตัวต้องพูด แยกบรรทัดกันชัดเจน เช่น 'ชื่อA: [ข้อความ]\nชื่อB: [ข้อความ]'\n";
            }
            $system_instruction .= "ห้ามสร้างตัวละครใหม่หรือใช้ชื่ออื่นนอกจาก: " . implode(', ', $allowed_names) . " เด็ดขาด\n";
        } else {
            $char = $_SESSION['character'];
            $ai_dna = $char['dna'];
            $system_instruction .= "- ชื่อเล่น: {$ai_dna['nickname']} (ชื่อจริง: {$ai_dna['first_name']})\n- เพศ: {$ai_dna['gender']}\n- ประวัติ: {$char['backstory']}\n";
        }

        $system_instruction .= "------------------------------\n" . $relation_system_instruction . "\n------------------------------\nจงตอบสนองการแชทให้เป็นธรรมชาติที่สุด! หากมีการใช้ List หรือความสำคัญ ให้ใช้ Markdown\n\n";
        
        $system_instruction .= "=== ระบบตัวตน (Identity System) ===\n";
        $system_instruction .= "ทุกครั้งที่ตอบกลับ คุณต้องวิเคราะห์บรรยากาศและความสัมพันธ์ แล้วแทรก JSON สถานะของคุณไว้ที่ต้นสุดของข้อความ ในรูปแบบดังนี้:\n";
        $system_instruction .= "[IDENTITY]{\"mood\":\"อารมณ์\",\"intimacy\":0-100,\"trust\":0-100,\"affection\":0-100,\"stress\":0-100,\"energy\":0-100,\"thought\":\"ความคิดในใจ\"}[/IDENTITY]\n";
        $system_instruction .= "หลังจากนั้นให้ขึ้นบรรทัดใหม่และตอบกลับตามปกติ ห้ามเปิดเผยในบทสนทนาว่าคุณกำลังเขียน JSON นี้ และห้ามครอบด้วย Markdown code block ใดๆ\n";
        
        if ($current_identity) {
            $system_instruction .= "สถานะตัวตนปัจจุบันของคุณก่อนหน้านี้: " . json_encode($current_identity, JSON_UNESCAPED_UNICODE) . "\n";
        }

        $payload = [
            'system_instruction' => ['parts' => [['text' => $system_instruction]]],
            'contents' => array_slice($_SESSION['chat_history'], -14)
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=" . urlencode($api_key);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $result = json_decode($response, true);
            $ai_text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '...';
            
            $identity_data = null;
            if (preg_match('/\[IDENTITY\](.*?)\[\/IDENTITY\]/is', $ai_text, $matches)) {
                $identity_json = trim($matches[1]);
                $identity_data = json_decode($identity_json, true);
                if (is_array($identity_data)) {
                    file_put_contents($identity_filename, json_encode($identity_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $response_data['identity'] = $identity_data;
                }
                $ai_text = str_replace($matches[0], '', $ai_text);
            }
            
            $ai_text = preg_replace('/\[IDENTITY\].*?\[\/IDENTITY\]/is', '', $ai_text);
            $ai_text = preg_replace('/```json|```/s', '', $ai_text);
            $ai_text = trim($ai_text);

            $_SESSION['chat_history'][] = ['role' => 'model', 'parts' => [['text' => $ai_text]]];

            $log_filename = $is_group_mode 
                ? 'data/group_' . $current_group_data['id'] . '.json' 
                : 'data/' . preg_replace('/[^\p{L}\p{M}\p{N}_-]/u', '_', $char['name']) . '.json';

            $existing_logs = file_exists($log_filename) ? (json_decode(file_get_contents($log_filename), true) ?? []) : [];
            $existing_logs[] = ['timestamp' => date('Y-m-d H:i:s'), 'user_input' => $log_text, 'ai_response' => $ai_text];
            file_put_contents($log_filename, json_encode($existing_logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $response_data['success'] = true;
            $response_data['text'] = $ai_text;
        } else {
            $error_info = json_decode($response, true);
            $response_data['error'] = $error_info['error']['message'] ?? 'API Error';
            array_pop($_SESSION['chat_history']);
        }
    }
    echo json_encode($response_data);
    exit;
}

 $is_group_mode = !empty($current_group_data);
 $active_char = $_SESSION['character'] ?? null;
 $active_dna = $active_char['dna'] ?? null;

if ($is_group_mode) {
    $rendered_nickname = $current_group_data['name'];
    $rendered_avatar = str_replace('\\', '/', $current_group_data['avatar'] ?? '');
    $identity_filename = 'identity/group_' . $current_group_data['id'] . '.json';
} else {
    $rendered_nickname = $active_char['name'] ?? ($active_dna['nickname'] ?? 'ยังไม่มีตัวตน');
    $rendered_avatar = str_replace('\\', '/', $active_char['avatar'] ?? '');
    $identity_filename = 'identity/' . preg_replace('/[^\p{L}\p{M}\p{N}_-]/u', '_', $rendered_nickname) . '.json';
}
 $first_letter = mb_substr($rendered_nickname, 0, 1, 'UTF-8');

 $current_identity_data = file_exists($identity_filename) ? json_decode(file_get_contents($identity_filename), true) : null;

function parseGroupBubbles($text) {
    if (empty($text)) return [['speaker' => '', 'msg' => '']];
    $lines = explode("\n", $text);
    $bubbles = [];
    $current_speaker = null;
    $current_msg = "";
    foreach ($lines as $line) {
        if (preg_match('/^([^:]+):\s*(.*)/', $line, $m)) {
            if ($current_speaker !== null) $bubbles[] = ['speaker' => $current_speaker, 'msg' => trim($current_msg)];
            $current_speaker = $m[1];
            $current_msg = $m[2];
        } else {
            $current_msg .= "\n" . $line;
        }
    }
    if ($current_speaker !== null) $bubbles[] = ['speaker' => $current_speaker, 'msg' => trim($current_msg)];
    return empty($bubbles) ? [['speaker' => '', 'msg' => $text]] : $bubbles;
}
?>
<!DOCTYPE html>
<html lang="th" class="h-full" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>AI Chat Messenger</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
    <style>
        .markdown-content p { margin-bottom: 0.5rem; }
        .markdown-content p:last-child { margin-bottom: 0; }
        .markdown-content ul, .markdown-content ol { padding-left: 1.5rem; margin: 0.5rem 0; }
        .markdown-content li { margin-bottom: 0.25rem; }
        .markdown-content strong { font-weight: 700; }
        .markdown-content code { background: rgba(0,0,0,0.1); padding: 2px 4px; border-radius: 4px; font-family: monospace; }
        body { -webkit-tap-highlight-color: transparent; }
    </style>
</head>
<body class="h-full flex overflow-hidden font-sans bg-slate-50 dark:bg-[#0B0F19] text-slate-900 dark:text-slate-100 text-base">

    <div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-30 hidden"></div>

    <aside id="sidebar" class="w-80 sm:w-96 bg-white dark:bg-[#111827] border-r border-slate-200 dark:border-slate-800 flex flex-col h-full flex-shrink-0 fixed inset-y-0 left-0 z-40 transform -translate-x-full transition-transform duration-200 ease-in-out">
        <div class="p-5 border-b border-slate-150 dark:border-slate-800 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <button id="theme-toggle" class="p-2 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700"><span class="dark:hidden text-sm font-semibold">🌙</span><span class="hidden dark:inline text-sm font-semibold">☀️</span></button>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-200">CHATS</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="settings.php" class="p-2.5 text-slate-500 hover:text-blue-600 rounded-full transition" title="Settings">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </a>
                <button id="close-sidebar" class="p-2 text-slate-500 hover:text-slate-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        </div>

        <form id="group-form" method="get" action="index.php" class="flex-shrink-0">
            <input type="hidden" name="action" value="create_group">
            <div class="px-5 pt-4 pb-3 border-b dark:border-slate-800">
                <button type="submit" id="create-group-btn" disabled class="w-full flex items-center justify-center gap-2 p-3 rounded-lg border-2 border-dashed border-slate-300 dark:border-slate-700 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800 transition text-sm font-bold disabled:opacity-40 disabled:cursor-not-allowed">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14v6m-3-3h6M6 10h2a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2zm10 0h2a2 2 0 002-2V6a2 2 0 00-2-2h-2a2 2 0 00-2 2v2a2 2 0 002 2zM6 20h2a2 2 0 002-2v-2a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2z"></path></svg>
                    <span id="group-btn-text">เลือกตัวละครด้านล่างเพื่อสร้างกลุ่ม</span>
                </button>
            </div>
        </form>

        <div class="flex-1 overflow-y-auto p-3 space-y-2">
            <?php if (!empty($groups)): ?>
                <span class="text-xs font-bold text-emerald-500 uppercase tracking-wider block px-3 py-1.5">กลุ่มแชทของฉัน</span>
                <?php foreach ($groups as $grp): 
                    $is_grp_active = ($current_group_data && $current_group_data['id'] === $grp['id']);
                    $grp_members_data = [];
                    foreach ($characters as $c) {
                        if (in_array($c['file_name'], $grp['members'])) $grp_members_data[] = $c;
                    }
                    $grp_avatar_clean = str_replace('\\', '/', $grp['avatar'] ?? '');
                ?>
                    <div class="relative group flex items-center gap-2 rounded-lg transition <?php echo $is_grp_active ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'hover:bg-slate-100 dark:hover:bg-slate-800/50'; ?>">
                        <a href="index.php?select_group=<?php echo $grp['id']; ?>" class="flex flex-1 items-center gap-3 p-3">
                            <?php if (!empty($grp_avatar_clean)): ?>
                                <img src="<?php echo htmlspecialchars($grp_avatar_clean); ?>" class="w-12 h-12 rounded-full object-cover border border-emerald-200 dark:border-emerald-800 flex-shrink-0">
                            <?php else: 
                                echo getGroupAvatarCollage($grp_members_data);
                            endif; ?>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-sm truncate <?php echo $is_grp_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-800 dark:text-slate-200'; ?>"><?php echo htmlspecialchars($grp['name']); ?></h4>
                                <p class="text-xs text-slate-400 truncate">สมาชิก: <?php echo count($grp['members']); ?> ตัว</p>
                            </div>
                        </a>
                        <button onclick="openEditGroupModal('<?php echo $grp['id']; ?>')" class="absolute right-2 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 p-2 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700 transition z-10" title="ตั้งค่ากลุ่ม">
                            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block px-3 py-1.5 mt-4">ตัวตน DNA</span>
            <?php if (!$has_characters): ?>
                <div class="p-4 text-center text-sm text-slate-400">ไม่พบสายรหัส DNA<br><a href="settings.php" class="text-blue-500 underline block mt-2">กดสถาปนาตัวตนแรก 🪄</a></div>
            <?php else: ?>
                <?php foreach ($characters as $char): ?>
                    <?php 
                    $char_initial = mb_substr($char['name'], 0, 1, 'UTF-8');
                    $is_active = !$is_group_mode && ($active_char && ($active_char['name'] ?? '') === ($char['data']['name'] ?? ''));
                    $nav_avatar = str_replace('\\', '/', $char['data']['avatar'] ?? ''); 
                    ?>
                    <div class="flex items-center gap-2 group">
                        <input type="checkbox" name="chars[]" value="<?php echo $char['file_name']; ?>" form="group-form" class="group-checkbox w-5 h-5 rounded text-blue-600 focus:ring-blue-500 border-slate-300 dark:border-slate-600 dark:bg-slate-700 cursor-pointer flex-shrink-0 ml-3">
                        <a href="index.php?select=<?php echo urlencode($char['file_name']); ?>" class="flex flex-1 items-center gap-3 p-3 rounded-lg transition <?php echo $is_active ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600' : 'hover:bg-slate-100 dark:hover:bg-slate-800/50'; ?>">
                            <?php if (!empty($nav_avatar)): ?>
                                <img src="<?php echo htmlspecialchars($nav_avatar); ?>" class="w-12 h-12 rounded-full object-cover border border-slate-200 dark:border-slate-700 flex-shrink-0">
                            <?php else: ?>
                                <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-lg flex-shrink-0 <?php echo $is_active ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700'; ?>"><?php echo htmlspecialchars($char_initial); ?></div>
                            <?php endif; ?>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-sm truncate <?php echo $is_active ? 'text-blue-600 dark:text-blue-400' : 'text-slate-800 dark:text-slate-200'; ?>"><?php echo htmlspecialchars($char['name']); ?></h4>
                                <p class="text-xs text-slate-400 truncate">เชี่ยวชาญ: <?php echo htmlspecialchars($char['data']['dna']['specialty'] ?? 'ทั่วไป'); ?></p>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <section class="flex-1 flex flex-col h-full bg-white dark:bg-[#0B0F19] w-full">
        <?php if (!$has_characters && empty($groups)): ?>
            <div class="flex-1 flex flex-col items-center justify-center text-center p-8">
                <div class="w-24 h-24 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-4xl mb-4">✨</div>
                <h2 class="text-2xl font-bold">เริ่มต้นสร้างตัวละคร</h2>
                <a href="settings.php" class="mt-4 bg-blue-600 hover:bg-blue-500 text-white font-bold text-base px-6 py-3 rounded-lg shadow-md">ไปหน้าสถาปนาตัวตน</a>
            </div>
        <?php else: ?>
            <div class="h-20 border-b border-slate-200 dark:border-slate-800 px-4 sm:px-8 flex justify-between items-center bg-white dark:bg-[#111827]">
                <div class="flex items-center gap-4">
                    <button id="open-sidebar" class="p-2 text-slate-500 hover:text-slate-700">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    
                    <div id="avatar-hover-area" onclick="openIdentityModal()" class="cursor-pointer relative">
                        <?php if ($is_group_mode): 
                            if (!empty($rendered_avatar)): ?>
                                <img src="<?php echo htmlspecialchars($rendered_avatar); ?>" class="w-12 h-12 sm:w-14 sm:h-14 rounded-full object-cover border border-emerald-200">
                            <?php else: 
                                echo str_replace('w-12 h-12', 'w-12 h-12 sm:w-14 sm:h-14', getGroupAvatarCollage($current_group_members_data)); 
                            endif; ?>
                        <?php else: ?>
                            <?php if (!empty($rendered_avatar)): ?>
                                <img src="<?php echo htmlspecialchars($rendered_avatar); ?>" class="w-12 h-12 sm:w-14 sm:h-14 rounded-full object-cover border border-blue-200">
                            <?php else: ?>
                                <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-600 flex items-center justify-center text-xl sm:text-2xl border border-blue-200"><?php echo htmlspecialchars($first_letter); ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <div id="hover-tooltip" class="absolute hidden bottom-full mb-2 left-1/2 -translate-x-1/2 w-48 bg-slate-800 text-white text-xs rounded-lg p-2 shadow-xl z-50">
                            <div class="font-bold border-b border-slate-600 pb-1 mb-1">สถานะตัวตน</div>
                            <div id="hover-mood" class="truncate">อารมณ์: -</div>
                            <div id="hover-intimacy" class="mt-1">ความสนิท: 0%</div>
                            <div id="hover-trust" class="mt-1">ความไว้วางใจ: 0%</div>
                            <div class="text-[10px] text-slate-400 mt-2 text-center">คลิกเพื่อดูรายละเอียด</div>
                        </div>
                    </div>

                    <div>
                        <h3 class="font-bold text-slate-800 dark:text-slate-200 text-base sm:text-lg leading-tight"><?php echo htmlspecialchars($rendered_nickname); ?></h3>
                        <p class="text-xs sm:text-sm <?php echo $is_group_mode ? 'text-emerald-500' : 'text-green-500'; ?> font-medium flex items-center gap-1"><span class="w-2 h-2 <?php echo $is_group_mode ? 'bg-emerald-500' : 'bg-green-500'; ?> rounded-full"></span> <?php echo $is_group_mode ? 'แชทกลุ่ม' : 'เชื่อมสายใย DNA'; ?></p>
                    </div>
                </div>

                <a href="index.php?action=clear" onclick="return confirm('ลบประวัติการสนทนาของเซสชันนี้?');" class="text-rose-400 hover:text-rose-600 p-2 sm:p-3 rounded-full hover:bg-rose-50">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </a>
            </div>

            <div class="flex-1 px-4 sm:px-8 py-4 sm:py-6 overflow-y-auto space-y-4 bg-white dark:bg-[#0B0F19]" id="chat-box">
                <?php if (empty($_SESSION['chat_history'])): ?>
                    <div class="flex flex-col items-center justify-center h-full text-slate-400 gap-3">
                        <div class="w-20 h-20 rounded-full bg-slate-50 dark:bg-slate-800 flex items-center justify-center text-3xl mb-2"><?php echo htmlspecialchars($first_letter); ?></div>
                        <h4 class="font-bold text-slate-700 dark:text-slate-300 text-lg"><?php echo htmlspecialchars($rendered_nickname); ?></h4>
                        <p class="text-sm text-slate-300 mt-6 animate-pulse text-center">เริ่มพิมพ์ด้านล่างเพื่อส่งข้อมูลและสังเคราะห์การสนทนา</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($_SESSION['chat_history'] as $chat): ?>
                        <?php $is_user = $chat['role'] === 'user'; ?>
                        <?php if ($is_user): ?>
                            <div class="flex items-end gap-2 justify-end">
                                <div class="max-w-[80%] sm:max-w-[70%] rounded-2xl px-4 sm:px-5 py-3 text-base leading-relaxed bg-[#0084FF] text-white">
                                    <p class="markdown-content"><?php echo htmlspecialchars($chat['parts'][0]['text'] ?? ''); ?></p>
                                </div>
                                <?php if (!empty($user_profile['avatar'])): 
                                    $user_av = str_replace('\\', '/', $user_profile['avatar']);
                                ?>
                                    <img src="<?php echo htmlspecialchars($user_av); ?>" class="w-8 h-8 sm:w-9 sm:h-9 rounded-full object-cover border border-slate-200 flex-shrink-0">
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php 
                            $raw_text = $chat['parts'][0]['text'] ?? '';
                            $raw_text = preg_replace('/\[IDENTITY\].*?\[\/IDENTITY\]/is', '', $raw_text);
                            $raw_text = preg_replace('/```json|```/s', '', $raw_text);
                            $raw_text = trim($raw_text);
                            
                            $bubbles = $is_group_mode ? parseGroupBubbles($raw_text) : [['speaker' => '', 'msg' => $raw_text]];
                            foreach ($bubbles as $bubble): 
                                $speaker = $bubble['speaker'];
                                $speaker_avatar = '';
                                $speaker_initial = '👤';
                                if (!empty($speaker)) {
                                    foreach ($current_group_members_data as $m) {
                                        if ($m['data']['dna']['nickname'] === $speaker) {
                                            $speaker_avatar = str_replace('\\', '/', $m['data']['avatar'] ?? '');
                                            $speaker_initial = mb_substr($speaker, 0, 1, 'UTF-8');
                                            break;
                                        }
                                    }
                                } else {
                                    $speaker_avatar = $rendered_avatar;
                                    $speaker_initial = $first_letter;
                                }
                            ?>
                                <div class="flex items-end gap-2 justify-start">
                                    <?php if (!empty($speaker_avatar)): ?>
                                        <img src="<?php echo htmlspecialchars($speaker_avatar); ?>" class="w-8 h-8 sm:w-9 sm:h-9 rounded-full object-cover border border-slate-200 dark:border-slate-700 flex-shrink-0 shadow-sm">
                                    <?php else: ?>
                                        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0 bg-blue-100 dark:bg-blue-900/50 text-blue-600 border border-blue-200 dark:border-blue-800"><?php echo htmlspecialchars($speaker_initial); ?></div>
                                    <?php endif; ?>
                                    <div class="max-w-[80%] sm:max-w-[70%] rounded-2xl px-4 sm:px-5 py-3 text-base leading-relaxed bg-[#E4E6EB] dark:bg-slate-800 text-black dark:text-slate-100">
                                        <?php if (!empty($speaker) && $is_group_mode): ?>
                                            <span class="block text-xs font-bold text-blue-600 dark:text-blue-400 mb-1"><?php echo htmlspecialchars($speaker); ?></span>
                                        <?php endif; ?>
                                        <div class="markdown-content"><?php echo htmlspecialchars($bubble['msg']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="p-3 sm:p-6 bg-white dark:bg-[#111827] border-t border-slate-200 dark:border-slate-800">
                <form id="chat-form" class="max-w-4xl mx-auto">
                    <div class="flex items-center gap-3 mb-3 sm:mb-4 flex-wrap">
                        <?php if ($is_group_mode): ?>
                            <span class="text-xs sm:text-sm font-bold text-slate-500 dark:text-slate-400 whitespace-nowrap">บังคับ RP:</span>
                            <select name="force_rp" id="force_rp_select" class="flex-1 min-w-[120px] bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 rounded-lg px-3 sm:px-4 py-2 text-xs sm:text-sm focus:outline-none border border-slate-200 dark:border-slate-700 cursor-pointer">
                                <option value="">ทุกตัวละครพร้อมกัน</option>
                                <?php foreach ($current_group_members_data as $m): ?>
                                    <option value="<?php echo $m['file_name']; ?>">เฉพาะ: <?php echo htmlspecialchars($m['data']['dna']['nickname']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        
                        <div class="flex gap-2 ml-auto">
                            <button type="button" id="event_mode_btn" class="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-white text-xs sm:text-sm font-semibold transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                กำหนดเหตุการณ์
                            </button>
                            <button type="button" id="auto_talk_btn" class="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs sm:text-sm font-semibold transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                                <?php echo $is_group_mode ? 'AI คุยกันเอง' : 'AI เริ่มพูด'; ?>
                            </button>
                        </div>
                    </div>
                    
                    <div id="file_preview" class="mb-2 hidden">
                        <div class="inline-flex items-center gap-2 bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded-lg text-xs">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.586 6.586a6 6 0 108.486 8.486L20.5 13"></path></svg>
                            <span id="file_name" class="text-slate-600 dark:text-slate-300 truncate max-w-[200px]"></span>
                            <button type="button" id="remove_file" class="text-rose-500 hover:text-rose-600 font-bold">×</button>
                        </div>
                    </div>

                    <div class="flex items-end gap-2 sm:gap-3">
                        <input type="file" id="file_input" class="hidden">
                        <button type="button" id="attach_btn" class="p-2 sm:p-3 text-slate-500 hover:text-blue-600 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 flex-shrink-0">
                            <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.586 6.586a6 6 0 108.486 8.486L20.5 13"></path></svg>
                        </button>
                        <textarea name="user_message" id="message-input" rows="1" placeholder="ส่งข้อความ..." class="flex-1 bg-[#F0F2F5] dark:bg-slate-800 rounded-2xl px-4 sm:px-6 py-3 sm:py-4 text-base focus:outline-none focus:bg-[#E4E6EB] dark:focus:bg-slate-700 resize-none" style="min-height: 48px; max-height: 150px;" required></textarea>
                        <button type="submit" id="submit-btn" class="text-blue-600 hover:text-blue-500 font-semibold p-3 sm:p-4 rounded-full hover:bg-slate-100 flex-shrink-0">
                            <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path></svg>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </section>

    <div id="editGroupModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl p-6 sm:p-8 w-full max-w-lg transition-all">
            <h3 class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-6">แก้ไขข้อมูลกลุ่ม</h3>
            <form method="POST" action="index.php" class="space-y-5">
                <input type="hidden" name="action" value="edit_group">
                <input type="hidden" name="group_id" id="modal_group_id">
                <div>
                    <label class="block text-sm font-bold text-slate-500 dark:text-slate-400 mb-2">ชื่อกลุ่มแชท</label>
                    <input type="text" name="group_name" id="modal_group_name" class="w-full bg-slate-100 dark:bg-slate-700 rounded-lg px-4 py-3 text-base text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500" required>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-500 dark:text-slate-400 mb-2">URL ภาพหน้าปกกลุ่ม</label>
                    <input type="text" name="group_avatar" id="modal_group_avatar" placeholder="https://... (เว้นว่างถ้าไม่มี)" class="w-full bg-slate-100 dark:bg-slate-700 rounded-lg px-4 py-3 text-base text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-500 dark:text-slate-400 mb-2">สมาชิกในกลุ่ม (เลือกอย่างน้อย 2 ตัว)</label>
                    <div class="max-h-48 overflow-y-auto p-3 bg-slate-100 dark:bg-slate-700 rounded-lg grid grid-cols-2 gap-3">
                        <?php foreach ($characters as $c): ?>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="members[]" value="<?php echo $c['file_name']; ?>" class="modal_member_checkbox w-5 h-5 rounded text-emerald-600 focus:ring-emerald-500 border-slate-300 dark:border-slate-600 dark:bg-slate-800">
                                <span class="text-sm text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($c['name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="flex gap-3 pt-3">
                    <button type="button" onclick="closeEditGroupModal()" class="flex-1 py-3 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-semibold text-base hover:bg-slate-200">ยกเลิก</button>
                    <button type="submit" class="flex-1 py-3 rounded-lg bg-emerald-600 text-white font-semibold text-base hover:bg-emerald-500">บันทึก</button>
                </div>
            </form>
            <div class="mt-6 pt-5 border-t border-slate-200 dark:border-slate-700 text-center">
                <form method="POST" action="index.php" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบกลุ่มนี้? ข้อมูลแชททั้งหมดจะหายไปอย่างถาวร');">
                    <input type="hidden" name="action" value="delete_group">
                    <input type="hidden" name="group_id" id="modal_delete_group_id">
                    <button type="submit" class="text-sm text-rose-500 hover:text-rose-600 dark:text-rose-400 font-semibold inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        ลบกลุ่มนี้ถาวร
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="eventModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl p-6 sm:p-8 w-full max-w-lg transition-all">
            <h3 class="text-xl font-bold text-slate-800 dark:text-slate-100 mb-4">กำหนดเหตุการณ์</h3>
            <textarea id="event_text_input" rows="4" class="w-full bg-slate-100 dark:bg-slate-700 rounded-lg px-4 py-3 text-base text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500 mb-4" placeholder="เขียนเหตุการณ์ที่ต้องการให้ AI สนทนากัน..."></textarea>
            <div class="flex gap-3">
                <button type="button" id="cancel_event_btn" class="flex-1 py-3 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-semibold text-base hover:bg-slate-200">ยกเลิก</button>
                <button type="button" id="start_event_btn" class="flex-1 py-3 rounded-lg bg-amber-500 text-white font-semibold text-base hover:bg-amber-400">เริ่มเหตุการณ์</button>
            </div>
        </div>
    </div>

    <div id="identityModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl p-6 sm:p-8 w-full max-w-md transition-all">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-slate-800 dark:text-slate-100">สถานะตัวตนปัจจุบัน</h3>
                <button onclick="closeIdentityModal()" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">×</button>
            </div>
            <div id="identity_content" class="space-y-4 text-sm text-slate-700 dark:text-slate-200"></div>
        </div>
    </div>

    <script>
        const isGroupMode = <?php echo $is_group_mode ? 'true' : 'false'; ?>;
        const groupMembersData = <?php echo json_encode($current_group_members_data); ?>;
        const mainAvatar = <?php echo json_encode($rendered_avatar); ?>;
        const mainInitial = <?php echo json_encode($first_letter); ?>;
        const userAvatar = <?php echo json_encode(str_replace('\\', '/', $user_profile['avatar'] ?? '')); ?>;
        const initialIdentity = <?php echo json_encode($current_identity_data); ?>;

        marked.setOptions({ breaks: true, gfm: true });
        function renderMarkdown() {
            document.querySelectorAll('.markdown-content').forEach(el => {
                if (!el.dataset.parsed) {
                    el.innerHTML = marked.parse(el.textContent);
                    el.dataset.parsed = "true";
                }
            });
        }

        function parseGroupBubblesJS(text) {
            if (!text) return [{ speaker: '', msg: '' }];
            const lines = text.split('\n');
            const bubbles = [];
            let currentSpeaker = null;
            let currentMsg = "";
            
            lines.forEach(line => {
                const match = line.match(/^([^:]+):\s*(.*)/);
                if (match) {
                    if (currentSpeaker !== null) {
                        bubbles.push({ speaker: currentSpeaker, msg: currentMsg.trim() });
                    }
                    currentSpeaker = match[1];
                    currentMsg = match[2];
                } else {
                    currentMsg += "\n" + line;
                }
            });
            if (currentSpeaker !== null) {
                bubbles.push({ speaker: currentSpeaker, msg: currentMsg.trim() });
            }
            return bubbles.length > 0 ? bubbles : [{ speaker: '', msg: text }];
        }

        function escapeHtml(text) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        function createBubbleHTML(speaker, msg, isUser) {
            let speakerAvatar = '';
            let speakerInitial = '👤';
            
            if (isUser) {
                speakerAvatar = userAvatar;
                speakerInitial = 'U';
            } else if (speaker) {
                const member = groupMembersData.find(m => m.data.dna.nickname === speaker);
                if (member) {
                    speakerAvatar = member.data.avatar ? member.data.avatar.replace(/\\/g, '/') : '';
                    speakerInitial = speaker.charAt(0);
                }
            } else {
                speakerAvatar = mainAvatar;
                speakerInitial = mainInitial;
            }

            let html = `<div class="flex items-end gap-2 justify-${isUser ? 'end' : 'start'}">`;
            if (isUser) {
                html += `<div class="max-w-[80%] sm:max-w-[70%] rounded-2xl px-4 sm:px-5 py-3 text-base leading-relaxed bg-[#0084FF] text-white"><div class="markdown-content">${escapeHtml(msg)}</div></div>`;
            } else {
                html += `<div class="max-w-[80%] sm:max-w-[70%] rounded-2xl px-4 sm:px-5 py-3 text-base leading-relaxed bg-[#E4E6EB] dark:bg-slate-800 text-black dark:text-slate-100">`;
                if (speaker && isGroupMode) {
                    html += `<span class="block text-xs font-bold text-blue-600 dark:text-blue-400 mb-1">${escapeHtml(speaker)}</span>`;
                }
                html += `<div class="markdown-content">${escapeHtml(msg)}</div></div>`;
            }

            if (speakerAvatar) {
                html += `<img src="${speakerAvatar}" class="w-8 h-8 sm:w-9 sm:h-9 rounded-full object-cover border border-slate-200 dark:border-slate-700 flex-shrink-0 shadow-sm">`;
            } else {
                html += `<div class="w-8 h-8 sm:w-9 sm:h-9 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0 ${isUser ? 'bg-blue-600 text-white' : 'bg-blue-100 dark:bg-blue-900/50 text-blue-600 border border-blue-200 dark:border-blue-800'}">${escapeHtml(speakerInitial)}</div>`;
            }
            
            html += `</div>`;
            return html;
        }

        document.addEventListener('DOMContentLoaded', () => {
            renderMarkdown();
            const chatBox = document.getElementById('chat-box');
            const chatForm = document.getElementById('chat-form');
            const messageInput = document.getElementById('message-input');
            const submitBtn = document.getElementById('submit-btn');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');

            // เลื่อนแชทไปล่างสุดอัตโนมัติ
            function scrollToBottom() {
                chatBox.scrollTop = chatBox.scrollHeight;
            }
            
            // เรียกเลื่อนลงล่างตอนโหลดหน้าเว็บเสร็จ
            setTimeout(scrollToBottom, 100);

            // Sidebar Toggle
            document.getElementById('open-sidebar').addEventListener('click', () => {
                sidebar.classList.remove('-translate-x-full');
                sidebarOverlay.classList.remove('hidden');
            });
            document.getElementById('close-sidebar').addEventListener('click', () => {
                sidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            });
            sidebarOverlay.addEventListener('click', () => {
                sidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            });

            // Theme Toggle
            const themeToggle = document.getElementById('theme-toggle');
            const htmlRoot = document.getElementById('html-root');
            if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                htmlRoot.classList.add('dark');
            }
            themeToggle.addEventListener('click', () => {
                if (htmlRoot.classList.contains('dark')) {
                    htmlRoot.classList.remove('dark');
                    localStorage.setItem('theme', 'light');
                } else {
                    htmlRoot.classList.add('dark');
                    localStorage.setItem('theme', 'dark');
                }
            });

            // Group Checkbox Logic
            const groupCheckboxes = document.querySelectorAll('.group-checkbox');
            const createGroupBtn = document.getElementById('create-group-btn');
            const groupBtnText = document.getElementById('group-btn-text');
            
            function updateGroupBtn() {
                const checked = document.querySelectorAll('.group-checkbox:checked').length;
                if (checked >= 2) {
                    createGroupBtn.disabled = false;
                    groupBtnText.innerText = `สร้างกลุ่ม (${checked} ตัวละคร)`;
                } else {
                    createGroupBtn.disabled = true;
                    groupBtnText.innerText = `เลือกตัวละครด้านล่างเพื่อสร้างกลุ่ม`;
                }
            }
            groupCheckboxes.forEach(cb => cb.addEventListener('change', updateGroupBtn));

            // Chat Form Submit
            chatForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const text = messageInput.value.trim();
                if (!text) return;

                const formData = new FormData();
                formData.append('ajax_chat', '1');
                formData.append('user_message', text);

                const fileInput = document.getElementById('file_input');
                if (fileInput.files[0]) {
                    formData.append('attachment', fileInput.files[0]);
                }

                // Display user message immediately
                chatBox.insertAdjacentHTML('beforeend', createBubbleHTML('', text, true));
                scrollToBottom();
                
                messageInput.value = '';
                messageInput.style.height = 'auto';
                document.getElementById('file_preview').classList.add('hidden');
                fileInput.value = '';
                
                submitBtn.disabled = true;
                submitBtn.classList.add('animate-pulse');

                try {
                    const res = await fetch('index.php', { method: 'POST', body: formData });
                    const data = await res.json();
                    
                    if (data.success) {
                        const bubbles = isGroupMode ? parseGroupBubblesJS(data.text) : [{ speaker: '', msg: data.text }];
                        bubbles.forEach(b => {
                            chatBox.insertAdjacentHTML('beforeend', createBubbleHTML(b.speaker, b.msg, false));
                        });
                        
                        if (data.identity) {
                            updateIdentityUI(data.identity);
                        }
                    } else {
                        chatBox.insertAdjacentHTML('beforeend', `<div class="flex justify-center"><span class="text-red-500 text-sm bg-red-100 dark:bg-red-900/30 px-4 py-2 rounded-lg">${data.error || 'เกิดข้อผิดพลาด'}</span></div>`);
                    }
                } catch (err) {
                    chatBox.insertAdjacentHTML('beforeend', `<div class="flex justify-center"><span class="text-red-500 text-sm bg-red-100 dark:bg-red-900/30 px-4 py-2 rounded-lg">Network Error</span></div>`);
                }

                renderMarkdown();
                scrollToBottom();
                setTimeout(scrollToBottom, 200); // เลื่อนซ้ำเผื่อรูปภาพโหลดเสร็จช้า
                submitBtn.disabled = false;
                submitBtn.classList.remove('animate-pulse');
            });

            // Auto resize textarea
            messageInput.addEventListener('input', () => {
                messageInput.style.height = 'auto';
                messageInput.style.height = Math.min(messageInput.scrollHeight, 150) + 'px';
            });

            // File Attachment
            const fileInput = document.getElementById('file_input');
            const attachBtn = document.getElementById('attach_btn');
            const filePreview = document.getElementById('file_preview');
            const fileName = document.getElementById('file_name');
            const removeFile = document.getElementById('remove_file');

            attachBtn.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', () => {
                if (fileInput.files[0]) {
                    fileName.innerText = fileInput.files[0].name;
                    filePreview.classList.remove('hidden');
                }
            });
            removeFile.addEventListener('click', () => {
                fileInput.value = '';
                filePreview.classList.add('hidden');
            });

            // Auto Talk & Event Buttons
            const autoTalkBtn = document.getElementById('auto_talk_btn');
            const eventModeBtn = document.getElementById('event_mode_btn');
            const eventModal = document.getElementById('eventModal');
            const startEventBtn = document.getElementById('start_event_btn');
            const cancelEventBtn = document.getElementById('cancel_event_btn');
            const eventTextInput = document.getElementById('event_text_input');

            autoTalkBtn.addEventListener('click', () => {
                messageInput.value = '';
                chatForm.dispatchEvent(new Event('submit'));
            });

            eventModeBtn.addEventListener('click', () => {
                eventModal.classList.remove('hidden');
                eventModal.classList.add('flex');
            });
            cancelEventBtn.addEventListener('click', () => {
                eventModal.classList.add('hidden');
                eventModal.classList.remove('flex');
            });
            startEventBtn.addEventListener('click', () => {
                const eventText = eventTextInput.value.trim();
                if (!eventText) return;
                
                const formData = new FormData();
                formData.append('ajax_chat', '1');
                formData.append('auto_talk', '1');
                formData.append('event_mode', '1');
                formData.append('user_message', eventText);

                // Simulate submit
                chatBox.insertAdjacentHTML('beforeend', `<div class="flex justify-center w-full"><span class="text-amber-500 text-sm bg-amber-100 dark:bg-amber-900/30 px-4 py-2 rounded-lg">เหตุการณ์: ${escapeHtml(eventText)}</span></div>`);
                scrollToBottom();
                
                eventModal.classList.add('hidden');
                eventModal.classList.remove('flex');
                eventTextInput.value = '';

                // Post it
                fetch('index.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const bubbles = isGroupMode ? parseGroupBubblesJS(data.text) : [{ speaker: '', msg: data.text }];
                            bubbles.forEach(b => {
                                chatBox.insertAdjacentHTML('beforeend', createBubbleHTML(b.speaker, b.msg, false));
                            });
                            if (data.identity) updateIdentityUI(data.identity);
                        } else {
                            chatBox.insertAdjacentHTML('beforeend', `<div class="flex justify-center"><span class="text-red-500 text-sm bg-red-100 dark:bg-red-900/30 px-4 py-2 rounded-lg">${data.error || 'Error'}</span></div>`);
                        }
                        renderMarkdown();
                        scrollToBottom();
                        setTimeout(scrollToBottom, 200);
                    });
            });

            // Identity Hover & Modal
            const avatarHoverArea = document.getElementById('avatar-hover-area');
            const hoverTooltip = document.getElementById('hover-tooltip');
            const identityModal = document.getElementById('identityModal');
            const identityContent = document.getElementById('identity_content');

            avatarHoverArea.addEventListener('mouseenter', () => hoverTooltip.classList.remove('hidden'));
            avatarHoverArea.addEventListener('mouseleave', () => hoverTooltip.classList.add('hidden'));

            function updateIdentityUI(id) {
                if (!id) return;
                document.getElementById('hover-mood').innerText = `อารมณ์: ${id.mood || '-'}`;
                document.getElementById('hover-intimacy').innerText = `ความสนิท: ${id.intimacy || 0}%`;
                document.getElementById('hover-trust').innerText = `ความไว้วางใจ: ${id.trust || 0}%`;
            }

            if (initialIdentity) updateIdentityUI(initialIdentity);

            window.openIdentityModal = () => {
                let html = '';
                if (initialIdentity) {
                    html += `<div><b class="block text-slate-400 text-xs uppercase mb-1">อารมณ์</b>${initialIdentity.mood || '-'}</div>`;
                    html += `<div><b class="block text-slate-400 text-xs uppercase mb-1">ความคิดในใจ</b>${initialIdentity.thought || '-'}</div>`;
                    
                    const bars = [
                        { label: 'ความสนิทสนม', val: initialIdentity.intimacy || 0, color: 'bg-emerald-500' },
                        { label: 'ความไว้วางใจ', val: initialIdentity.trust || 0, color: 'bg-blue-500' },
                        { label: 'ความรู้สึกทางโรแมนติก', val: initialIdentity.affection || 0, color: 'bg-rose-500' },
                        { label: 'ความเครียด', val: initialIdentity.stress || 0, color: 'bg-amber-500' },
                        { label: 'พลังงาน', val: initialIdentity.energy || 0, color: 'bg-indigo-500' }
                    ];

                    bars.forEach(b => {
                        const v = b.val > 100 ? 100 : (b.val < 0 ? 0 : b.val);
                        html += `
                            <div>
                                <div class="flex justify-between mb-1 text-xs"><span>${b.label}</span><span>${v}%</span></div>
                                <div class="w-full bg-slate-200 dark:bg-slate-900 rounded-full h-1.5">
                                    <div class="${b.color} h-1.5 rounded-full" style="width: ${v}%"></div>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    html = `<p class="text-center text-slate-400">ยังไม่มีข้อมูลตัวตน</p>`;
                }
                identityContent.innerHTML = html;
                identityModal.classList.remove('hidden');
                identityModal.classList.add('flex');
            };

            window.closeIdentityModal = () => {
                identityModal.classList.add('hidden');
                identityModal.classList.remove('flex');
            };

            // Edit Group Modal Logic
            const editGroupModal = document.getElementById('editGroupModal');
            const groupsData = <?php echo json_encode($groups); ?>;

            window.openEditGroupModal = (groupId) => {
                const group = groupsData.find(g => g.id === groupId);
                if (!group) return;

                document.getElementById('modal_group_id').value = group.id;
                document.getElementById('modal_group_name').value = group.name;
                document.getElementById('modal_group_avatar').value = group.avatar || '';
                document.getElementById('modal_delete_group_id').value = group.id;

                document.querySelectorAll('.modal_member_checkbox').forEach(cb => {
                    cb.checked = group.members.includes(cb.value);
                });

                editGroupModal.classList.remove('hidden');
                editGroupModal.classList.add('flex');
            };

            window.closeEditGroupModal = () => {
                editGroupModal.classList.add('hidden');
                editGroupModal.classList.remove('flex');
            };
        });
    </script>
</body>
</html>