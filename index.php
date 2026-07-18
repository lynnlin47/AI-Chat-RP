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

if (isset($_GET['select'])) {
    $target_file = $_GET['select'];
    foreach ($characters as $char) {
        if ($char['file_name'] === $target_file) {
            $_SESSION['character'] = $char['data'];
            $_SESSION['chat_history'] = [];
            
            $log_filename = 'data/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $char['data']['name']) . '.json';
            if (file_exists($log_filename)) {
                $historical_logs = json_decode(file_get_contents($log_filename), true);
                if (is_array($historical_logs)) {
                    foreach ($historical_logs as $log) {
                        $_SESSION['chat_history'][] = ['role' => 'user', 'parts' => [['text' => $log['user_input']]]];
                        $_SESSION['chat_history'][] = ['role' => 'model', 'parts' => [['text' => $log['ai_response']]]];
                    }
                }
            }
            header('Location: index.php');
            exit;
        }
    }
}

if ($has_characters) {
    if (!isset($_SESSION['character'])) {
        $_SESSION['character'] = $characters[0]['data'];
        $log_filename = 'data/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $_SESSION['character']['name']) . '.json';
        $_SESSION['chat_history'] = [];
        if (file_exists($log_filename)) {
            $historical_logs = json_decode(file_get_contents($log_filename), true);
            if (is_array($historical_logs)) {
                foreach ($historical_logs as $log) {
                    $_SESSION['chat_history'][] = ['role' => 'user', 'parts' => [['text' => $log['user_input']]]];
                    $_SESSION['chat_history'][] = ['role' => 'model', 'parts' => [['text' => $log['ai_response']]]];
                }
            }
        }
    }
} else {
    unset($_SESSION['character']);
    $_SESSION['chat_history'] = [];
}

if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    $_SESSION['chat_history'] = [];
    if (isset($_SESSION['character'])) {
        $log_filename = 'data/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $_SESSION['character']['name']) . '.json';
        if (file_exists($log_filename)) {
            unlink($log_filename);
        }
    }
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_message'])) {
    $user_text = trim($_POST['user_message']);

    if (empty($api_key)) {
        $error_message = 'กรุณาตั้งค่า API Key ในหน้ากำหนดรหัสพันธุกรรมของคุณก่อน';
    } elseif (!empty($user_text) && isset($_SESSION['character'])) {
        $_SESSION['chat_history'][] = ['role' => 'user', 'parts' => [['text' => $user_text]]];
        $char = $_SESSION['character'];
        $ai_dna = $char['dna'];
        $user_has_dna = !empty($user_profile['dna']['nickname']);
        
        if ($user_has_dna) {
            $u_dna = $user_profile['dna'];
            $relation_system_instruction = "นี่คือข้อมูลของผู้ใช้ที่คุณสนิทสนมด้วยมากที่สุด จงใช้วิเคราะห์เพื่อปรับอารมณ์และการพูดคุยให้เข้าคู่กัน:\n" .
                                          "- เขาชื่อเล่นว่า {$u_dna['nickname']} (ชื่อจริง {$u_dna['first_name']} {$u_dna['last_name']})\n" .
                                          "- เพศของผู้ใช้: {$u_dna['gender']} (หากเขาเป็นผู้หญิงจงพูดคุยอย่างถนอมจิตใจ หากเขาเป็นผู้ชายจงเข้าหาตามขีดระดับอารมณ์ของคุณ)\n" .
                                          "- วันเดือนปีเกิด: {$u_dna['birthdate']} (เปรียบเทียบระยะเวลาอายุก่อนตอบสนองเพื่อระดับความอาวุโสที่ถูกต้อง)\n" .
                                          "- สภาพร่างกาย: สีตา {$u_dna['eye_color']}, ผมสี {$u_dna['hair_color']}, ผิวสี {$u_dna['skin_color']}\n" .
                                          "- โรคประจำตัวของผู้ใช้: {$u_dna['medical_conditions']} (จงเอาใจใส่เป็นพิเศษหากเรื่องคุยกระทบกับข้อจำกัดสุขภาพของเขา)\n" .
                                          "- ความสามารถของผู้ใช้: {$u_dna['specialty']}\n" .
                                          "- ประวัติความเป็นมาของเขากับคุณ: {$user_profile['backstory']}\n" .
                                          "สถานะความสัมพันธ์: 'คนสนิทกันมากที่สุด' ดังนั้นคุณต้องตอบแบบเป็นธรรมชาติ ไร้ความกังวล ห้ามตอบสุภาพเหมือนคนแปลกหน้า สวมกอดความคุ้นเคยนี้ทันที";
        } else {
            $relation_system_instruction = "สถานะความสัมพันธ์: 'คนเพิ่งรู้จักกันและไม่เคยพบกันมาก่อน'\n" .
                                          "ผู้ใช้ยังไม่ได้เปิดเผยสายรหัส DNA ดังนั้น คุณต้องสนทนารักษาระยะห่าง ค่อนข้างสุภาพ เรียบร้อย และระมัดระวังเหมือนมีกำแพงปิดกั้นเล็กน้อยตามธรรมชาติ";
        }

        $system_instruction = "คุณคือมนุษย์ที่มีชีวิตและประวัติตามรหัสพันธุกรรม (DNA) ดังต่อไปนี้ ห้ามหลุดสวมบทบาทหรือเปิดเผยเด็ดขาดว่าตัวเองคือปัญญาประดิษฐ์ (AI):\n" .
                              "- ชื่อจริง: {$ai_dna['first_name']} นามสกุล {$ai_dna['last_name']} (เรียกฉันสั้นๆ ด้วยชื่อเล่นว่า: {$ai_dna['nickname']})\n" .
                              "- เพศทางกายภาพ: {$ai_dna['gender']} (หากเป็นเพศหญิงให้ใช้คำลงท้ายประเภท คะ/ค่ะ/จ๋า อย่างเป็นธรรมชาติสูงสุด หากเป็นเพศชายให้ใช้ครับ/ฮะ/ผม)\n" .
                              "- วันเดือนปีเกิด: {$ai_dna['birthdate']} (ให้คำนวณระดับความอาวุโส ความคิด และอายุของคุณอ้างอิงจากวันเกิดนี้เสมอ)\n" .
                              "- ร่างกายภายนอก: สีตาคือ {$ai_dna['eye_color']}, สีผมคือ {$ai_dna['hair_color']}, สีผิวคือ {$ai_dna['skin_color']}\n" .
                              "- ระดับสารเคมีในร่างกาย (ฮอร์โมนเพศ): เพศชาย {$ai_dna['hormones']['male']}% และเพศหญิง {$ai_dna['hormones']['female']}%\n" .
                              "  (ระดับฮอร์โมนด้านบนจะแสดงถึงโครงสร้างน้ำเสียง อารมณ์ฉุนเฉียว การตัดสินใจ ยิ่งฮอร์โมนฝั่งไหนเด่น พฤติกรรมจะเอนเอียงไปตามฮอร์โมนฝั่งนั้นอย่างมีนัยสำคัญ)\n" .
                              "- ข้อจำกัดทางกายภาพ/โรคประจำตัว/สิ่งเร้าที่กลัวรุนแรง: {$ai_dna['medical_conditions']} (คุณจะมีปฏิกิริยาและขีดจำกัดทางร่างกายตามนี้ทันที หากบทสนทนามีประเด็นที่อ่อนไหวหรือไปกระตุ้นมัน)\n" .
                              "- ทักษะความเชี่ยวชาญเฉพาะทางสูงสุด: {$ai_dna['specialty']} (คุณเป็นยอดฝีมือขั้นสูงในเรื่องนี้ การคิดเชิงวิเคราะห์และการแสดงออกในเชิงวิชาการของคุณในวิชานี้จะไร้ที่ติและมีความลึกซึ้งเป็นพิเศษ)\n" .
                              "- ประวัติชีวิตเบื้องหลังที่เป็นตัวขับเคลื่อนพฤติกรรมของคุณ: {$char['backstory']}\n\n" .
                              "------------------------------\n" .
                              $relation_system_instruction . "\n" .
                              "------------------------------\n" .
                              "จงตอบสนองการแชทโดยนำมิติรหัส DNA ของทั้งสองฝั่งมาสังเคราะห์และสะท้อนมุมมองออกมาให้เป็นธรรมชาติที่สุด!";

        $history_to_send = array_slice($_SESSION['chat_history'], -14);
        $payload = [
            'system_instruction' => [
                'parts' => [
                    ['text' => $system_instruction]
                ]
            ],
            'contents' => $history_to_send
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
            $_SESSION['chat_history'][] = ['role' => 'model', 'parts' => [['text' => $ai_text]]];

            $log_filename = 'data/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $char['name']) . '.json';
            $existing_logs = [];
            if (file_exists($log_filename)) {
                $existing_logs = json_decode(file_get_contents($log_filename), true) ?? [];
            }
            
            $existing_logs[] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'user_input' => $user_text,
                'ai_response' => $ai_text
            ];
            
            file_put_contents($log_filename, json_encode($existing_logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        } else {
            $error_info = json_decode($response, true);
            $error_message = $error_info['error']['message'] ?? 'API Gateway Error';
            array_pop($_SESSION['chat_history']);
        }
    }
}

$active_char = $_SESSION['character'] ?? null;
$active_dna = $active_char['dna'] ?? null;
$rendered_nickname = $active_char['name'] ?? ($active_dna['nickname'] ?? 'ยังไม่มีตัวตนในเซสชัน');
$rendered_first_name = $active_dna['first_name'] ?? 'ไม่ระบุข้อมูล';
$rendered_last_name = $active_dna['last_name'] ?? '';
$rendered_gender = $active_dna['gender'] ?? 'ไม่ระบุ';
$rendered_specialty = $active_dna['specialty'] ?? 'ความสามารถทั่วไป';
$rendered_avatar = $active_char['avatar'] ?? '';
$first_letter = mb_substr($rendered_nickname, 0, 1, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="th" class="h-full" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chat Messenger</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
</head>
<body class="h-full flex overflow-hidden font-sans bg-slate-50 dark:bg-[#0B0F19] text-slate-900 dark:text-slate-100">

    <aside class="w-80 bg-white dark:bg-[#111827] border-r border-slate-200 dark:border-slate-800 flex flex-col h-full flex-shrink-0">
        <div class="p-4 border-b border-slate-150 dark:border-slate-800 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <button id="theme-toggle" class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700" title="เปลี่ยนสีเว็บ">
                    <span class="dark:hidden text-xs font-semibold">🌙 ค่ำ</span>
                    <span class="hidden dark:inline text-xs font-semibold">☀️ วัน</span>
                </button>
                <h1 class="text-base font-bold text-slate-800 dark:text-slate-200">CHATS</h1>
            </div>
            
            <div class="flex gap-1.5">
                <a href="settings.php" class="p-2 text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-full transition" title="ไปหน้าควบคุม/ตั้งค่าผู้ใช้">
                    <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </a>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-2 space-y-1">
            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider block px-3 py-1.5">ตัวตน DNA ในโฟลเดอร์</span>
            
            <?php if (!$has_characters): ?>
                <div class="p-4 text-center text-xs text-slate-400 dark:text-slate-500">
                    ไม่พบสายรหัส DNA เลย<br>
                    <a href="settings.php" class="text-blue-500 hover:text-blue-400 font-semibold underline block mt-2">กดสถาปนาตัวตนแรก 🪄</a>
                </div>
            <?php else: ?>
                <?php foreach ($characters as $char): ?>
                    <?php 
                    $char_initial = mb_substr($char['name'], 0, 1, 'UTF-8');
                    $is_active = ($active_char && ($active_char['name'] ?? '') === ($char['data']['name'] ?? ''));
                    $nav_avatar = $char['data']['avatar'] ?? '';
                    ?>
                    <a href="index.php?select=<?php echo urlencode($char['file_name']); ?>" 
                       class="flex items-center gap-3 p-3 rounded-lg transition <?php echo $is_active ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/50'; ?>">
                        
                        <?php if (!empty($nav_avatar)): ?>
                            <img src="<?php echo htmlspecialchars($nav_avatar); ?>" class="w-10 h-10 rounded-full object-cover border <?php echo $is_active ? 'border-blue-400' : 'border-slate-200 dark:border-slate-700'; ?>">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm <?php echo $is_active ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700'; ?>">
                                <?php echo htmlspecialchars($char_initial); ?>
                            </div>
                        <?php endif; ?>

                        <div class="flex-1 min-w-0">
                            <h4 class="font-bold text-xs truncate <?php echo $is_active ? 'text-blue-600 dark:text-blue-400' : 'text-slate-800 dark:text-slate-200'; ?>"><?php echo htmlspecialchars($char['name']); ?></h4>
                            <p class="text-[10px] text-slate-400 dark:text-slate-500 truncate">เชี่ยวชาญ: <?php echo htmlspecialchars($char['data']['dna']['specialty'] ?? 'ทั่วไป'); ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <section class="flex-1 flex flex-col h-full bg-white dark:bg-[#0B0F19]">
        
        <?php if (!$has_characters): ?>
            <div class="flex-1 flex flex-col items-center justify-center text-center p-8 bg-slate-50 dark:bg-[#0B0F19]">
                <div class="w-20 h-20 rounded-full bg-blue-100 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 flex items-center justify-center text-3xl mb-4">
                    ✨
                </div>
                <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">เริ่มต้นสร้างลูกของคุณ</h2>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-2 max-w-sm leading-relaxed">
                    ขณะนี้ไม่มีตัวตน AI ในฐานข้อมูล มากำหนดรหัสพันธุกรรมร่วมกันเพื่อสร้างสายสัมพันธ์เชิงลึก
                </p>
                <a href="settings.php" class="mt-4 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs px-5 py-2.5 rounded-lg shadow-md inline-flex items-center gap-1.5">
                    <span>🪄</span> ไปหน้าสถาปนาตัวตนเพื่อเริ่มต้น
                </a>
            </div>
        <?php else: ?>
            <div class="h-16 border-b border-slate-200 dark:border-slate-800 px-6 flex justify-between items-center bg-white dark:bg-[#111827]">
                <div class="flex items-center gap-3">
                    <?php if (!empty($rendered_avatar)): ?>
                        <img src="<?php echo htmlspecialchars($rendered_avatar); ?>" class="w-10 h-10 rounded-full object-cover border border-blue-200 dark:border-blue-800">
                    <?php else: ?>
                        <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-lg border border-blue-200 dark:border-blue-800">
                            <?php echo htmlspecialchars($first_letter); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <h3 class="font-bold text-slate-800 dark:text-slate-200 text-sm leading-tight"><?php echo htmlspecialchars($rendered_nickname); ?></h3>
                        <p class="text-[11px] text-green-500 font-medium flex items-center gap-1">
                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full inline-block"></span> เชื่อมสายใย DNA สำเร็จ
                        </p>
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    <a href="settings.php" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs px-3.5 py-1.5 rounded-lg flex items-center gap-1 shadow-sm mr-2">
                        <span>+</span> สร้างเพิ่ม
                    </a>
                    <a href="index.php?action=clear" onclick="return confirm('คุณต้องการลบประวัติการสนทนาทั้งหมดของตัวละครนี้และไฟล์บันทึกใช่หรือไม่?');" class="text-rose-400 dark:text-rose-500 hover:text-rose-600 dark:hover:text-rose-300 p-2 rounded-full hover:bg-rose-50 dark:hover:bg-rose-900/30">
                        <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </a>
                </div>
            </div>

            <div class="flex-1 px-6 py-4 overflow-y-auto space-y-3 bg-white dark:bg-[#0B0F19]" id="chat-box">
                <?php if (empty($_SESSION['chat_history'])): ?>
                    <div class="flex flex-col items-center justify-center h-full text-slate-400 dark:text-slate-500 gap-2">
                        <?php if (!empty($rendered_avatar)): ?>
                            <img src="<?php echo htmlspecialchars($rendered_avatar); ?>" class="w-20 h-20 rounded-full object-cover border-4 border-slate-100 dark:border-slate-800 shadow-sm mb-2">
                        <?php else: ?>
                            <div class="w-16 h-16 rounded-full bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-2xl mb-2">
                                <?php echo htmlspecialchars($first_letter); ?>
                            </div>
                        <?php endif; ?>
                        
                        <h4 class="font-bold text-slate-700 dark:text-slate-300 text-md"><?php echo htmlspecialchars($rendered_nickname); ?></h4>
                        
                        <div class="text-xs text-slate-400 dark:text-slate-500 text-center max-w-sm leading-relaxed space-y-1">
                            <p class="font-semibold text-slate-600 dark:text-slate-400">ชื่อจริง: <?php echo htmlspecialchars($rendered_first_name . ' ' . $rendered_last_name); ?></p>
                            <p class="text-[11px]">เพศทางกายภาพ: <?php echo htmlspecialchars($rendered_gender); ?></p>
                            <p class="text-[11px] px-3 py-1 bg-slate-50 dark:bg-slate-900 rounded-lg inline-block border border-slate-100 dark:border-slate-800 mt-2">
                                ความเชี่ยวชาญเฉพาะทาง: <?php echo htmlspecialchars($rendered_specialty); ?>
                            </p>
                        </div>
                        <p class="text-[11px] text-slate-300 dark:text-slate-700 mt-6 animate-pulse">เริ่มพิมพ์ด้านล่างเพื่อส่งข้อมูลและสังเคราะห์การสนทนา</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($_SESSION['chat_history'] as $chat): ?>
                        <?php 
                        $is_user = $chat['role'] === 'user';
                        $text = htmlspecialchars($chat['parts'][0]['text']);
                        ?>
                        <div class="flex items-end gap-2 <?php echo $is_user ? 'justify-end' : 'justify-start'; ?>">
                            <?php if (!$is_user): ?>
                                <?php if (!empty($rendered_avatar)): ?>
                                    <img src="<?php echo htmlspecialchars($rendered_avatar); ?>" class="w-7 h-7 rounded-full object-cover border border-slate-200 dark:border-slate-700 flex-shrink-0 shadow-sm">
                                <?php else: ?>
                                    <div class="w-7 h-7 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-xs flex-shrink-0 border border-blue-200 dark:border-blue-800">
                                        <?php echo htmlspecialchars($first_letter); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <div class="max-w-[65%] rounded-2xl px-4 py-2 text-sm leading-relaxed <?php echo $is_user ? 'bg-[#0084FF] text-white rounded-br-md rounded-tr-xl rounded-bl-xl rounded-tl-xl shadow-sm' : 'bg-[#E4E6EB] dark:bg-slate-800 text-black dark:text-slate-100 rounded-bl-md rounded-tr-xl rounded-tl-xl rounded-br-xl shadow-xs'; ?>">
                                <p class="whitespace-pre-line"><?php echo $text; ?></p>
                            </div>
                            
                            <?php if ($is_user && !empty($user_profile['avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($user_profile['avatar']); ?>" class="w-6 h-6 rounded-full object-cover border border-slate-200">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="mx-auto max-w-md bg-rose-50 dark:bg-rose-950/40 border border-rose-100 dark:border-rose-900/50 text-rose-600 dark:text-rose-400 p-3 rounded-xl text-xs text-center">
                        ⚠️ <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="p-4 bg-white dark:bg-[#111827] border-t border-slate-200 dark:border-slate-800">
                <form id="chat-form" method="POST" action="index.php" class="max-w-4xl mx-auto flex items-end gap-2">
                    <label class="p-2.5 text-slate-500 hover:text-blue-600 cursor-pointer transition flex-shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.586 6.586a6 6 0 108.486 8.486L20 13"></path></svg>
                        <input type="file" class="hidden" accept="image/*,.pdf,.doc,.docx,.txt">
                    </label>
                    <textarea name="user_message" id="message-input" rows="1" placeholder="ส่งข้อความคุยกับตัวตนสะท้อนกลับ..." 
                              class="flex-1 bg-[#F0F2F5] dark:bg-slate-800 hover:bg-[#E4E6EB] dark:hover:bg-slate-700 rounded-2xl px-5 py-3 text-sm focus:outline-none focus:bg-[#E4E6EB] dark:focus:bg-slate-700 text-slate-800 dark:text-slate-100 placeholder-slate-500 dark:placeholder-slate-400 resize-none overflow-hidden" 
                              style="min-height: 48px; max-height: 150px;" required></textarea>
                    <button type="submit" class="text-blue-600 dark:text-blue-400 hover:text-blue-500 dark:hover:text-blue-300 font-semibold p-3 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 flex-shrink-0" title="ส่งแชท">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path>
                        </svg>
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </section>

    <script>
        const themeToggle = document.getElementById('theme-toggle');
        const htmlRoot = document.getElementById('html-root');
        const textarea = document.getElementById('message-input');
        const form = document.getElementById('chat-form');

        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            htmlRoot.classList.add('dark');
        }

        themeToggle.addEventListener('click', () => {
            htmlRoot.classList.toggle('dark');
            localStorage.setItem('theme', htmlRoot.classList.contains('dark') ? 'dark' : 'light');
        });

        textarea.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                form.submit();
            }
        });

        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        const chatBox = document.getElementById('chat-box');
        if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
    </script>
</body>
</html>