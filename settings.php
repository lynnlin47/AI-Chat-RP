<?php
session_start();

 $required_dirs = ['characters', 'characters/avatars', 'user_profile', 'data', 'identity'];
foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect_url = 'settings.php';
    $query_params = [];

    if (isset($_POST['action']) && $_POST['action'] === 'save_user_profile') {
        $api_key = trim($_POST['api_key'] ?? '');
        $first_name = trim($_POST['user_first_name'] ?? '');
        $nickname = trim($_POST['user_nickname'] ?? '');
        $last_name = trim($_POST['user_last_name'] ?? '');
        $gender = trim($_POST['user_gender'] ?? '');
        $birthdate = trim($_POST['user_birthdate'] ?? '');
        $personality = trim($_POST['user_personality'] ?? '');
        $eye_color = trim($_POST['user_eye_color'] ?? '');
        $hair_color = trim($_POST['user_hair_color'] ?? '');
        $skin_color = trim($_POST['user_skin_color'] ?? '');
        $medical_conditions = trim($_POST['user_medical_conditions'] ?? '');
        $hormone_male = (int)($_POST['user_hormone_male'] ?? 50);
        $hormone_female = (int)($_POST['user_hormone_female'] ?? 50);
        $specialty = trim($_POST['user_specialty'] ?? '');
        $backstory = trim($_POST['user_backstory'] ?? '');
        
        $avatar_url = '';
        $existing_user_data = json_decode(@file_get_contents('user_profile/user_dna.json'), true);
        if (isset($existing_user_data['avatar']) && !empty($existing_user_data['avatar'])) {
            $avatar_url = $existing_user_data['avatar'];
        }

        if (isset($_FILES['user_avatar']) && $_FILES['user_avatar']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['user_avatar']['name'], PATHINFO_EXTENSION);
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array(strtolower($ext), $allowed)) {
                $new_name = 'user_avatar_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['user_avatar']['tmp_name'], 'user_profile/' . $new_name)) {
                    if (!empty($avatar_url) && file_exists($avatar_url)) {
                        unlink($avatar_url);
                    }
                    $avatar_url = 'user_profile/' . $new_name;
                }
            }
        }
        
        $avatar_url = str_replace('\\', '/', $avatar_url);

        if (($hormone_male === 100 && $hormone_female === 100) || ($hormone_male === 0 && $hormone_female === 0)) {
            $query_params['error'] = 'ระดับฮอร์โมนของคุณไม่สามารถเป็น 100:100 หรือ 0:0 ได้';
        } else {
            $user_data = [
                'api_key' => $api_key,
                'avatar' => $avatar_url,
                'name' => $nickname,
                'dna' => [
                    'first_name' => $first_name,
                    'nickname' => $nickname,
                    'last_name' => $last_name,
                    'gender' => $gender,
                    'birthdate' => $birthdate,
                    'personality' => $personality,
                    'eye_color' => $eye_color,
                    'hair_color' => $hair_color,
                    'skin_color' => $skin_color,
                    'medical_conditions' => $medical_conditions,
                    'hormones' => [
                        'male' => $hormone_male,
                        'female' => $hormone_female
                    ],
                    'specialty' => $specialty
                ],
                'backstory' => $backstory
            ];

            if (file_put_contents('user_profile/user_dna.json', json_encode($user_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                $_SESSION['api_key'] = $api_key;
                $query_params['message'] = 'บันทึกสายรหัส DNA และ API Key ของผู้ใช้งานเรียบร้อยแล้ว!';
            } else {
                $query_params['error'] = 'ไม่สามารถบันทึกข้อมูลผู้ใช้ลงไฟล์ระบบได้';
            }
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'save_character') {
        $old_filename = trim($_POST['old_filename'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $nickname = trim($_POST['nickname'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $birthdate = trim($_POST['birthdate'] ?? '');
        $personality = trim($_POST['personality'] ?? '');
        $eye_color = trim($_POST['eye_color'] ?? '');
        $hair_color = trim($_POST['hair_color'] ?? '');
        $skin_color = trim($_POST['skin_color'] ?? '');
        $medical_conditions = trim($_POST['medical_conditions'] ?? '');
        $hormone_male = (int)($_POST['hormone_male'] ?? 50);
        $hormone_female = (int)($_POST['hormone_female'] ?? 50);
        $specialty = trim($_POST['specialty'] ?? '');
        $backstory = trim($_POST['backstory'] ?? '');

        $ai_avatar_url = trim($_POST['existing_avatar'] ?? '');
        
        if (isset($_FILES['ai_avatar']) && $_FILES['ai_avatar']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['ai_avatar']['name'], PATHINFO_EXTENSION);
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array(strtolower($ext), $allowed)) {
                $new_name = 'ai_avatar_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                if (move_uploaded_file($_FILES['ai_avatar']['tmp_name'], 'characters/avatars/' . $new_name)) {
                    if (!empty($ai_avatar_url) && file_exists($ai_avatar_url) && strpos($ai_avatar_url, 'characters/avatars/') !== false) {
                        unlink($ai_avatar_url);
                    }
                    $ai_avatar_url = 'characters/avatars/' . $new_name;
                }
            }
        }
        
        $ai_avatar_url = str_replace('\\', '/', $ai_avatar_url);

        $new_filename = 'characters/' . preg_replace('/[^\p{L}\p{M}\p{N}_-]/u', '_', $nickname) . '.json';

        if (($hormone_male === 100 && $hormone_female === 100) || ($hormone_male === 0 && $hormone_female === 0)) {
            $query_params['error'] = 'ระดับฮอร์โมนของตัวละครไม่สามารถเป็น 100:100 หรือ 0:0 ได้';
        } elseif (empty($nickname) || empty($first_name)) {
            $query_params['error'] = 'กรุณากรอกชื่อจริงและชื่อเล่นของตัวละคร AI';
        } elseif (empty($old_filename) && file_exists($new_filename)) {
            $query_params['error'] = 'ชื่อเล่นนี้ถูกใช้งานแล้ว กรุณาใช้ชื่ออื่น';
        } else {
            if (!empty($old_filename) && $old_filename !== $new_filename && file_exists($old_filename)) {
                $old_identity_file = 'identity/' . basename($old_filename);
                $new_identity_file = 'identity/' . basename($new_filename);
                if (file_exists($old_identity_file)) {
                    rename($old_identity_file, $new_identity_file);
                }
                unlink($old_filename);
            }
            
            $char_data = [
                'name' => $nickname,
                'avatar' => $ai_avatar_url,
                'dna' => [
                    'first_name' => $first_name,
                    'nickname' => $nickname,
                    'last_name' => $last_name,
                    'gender' => $gender,
                    'birthdate' => $birthdate,
                    'personality' => $personality,
                    'eye_color' => $eye_color,
                    'hair_color' => $hair_color,
                    'skin_color' => $skin_color,
                    'medical_conditions' => $medical_conditions,
                    'hormones' => [
                        'male' => $hormone_male,
                        'female' => $hormone_female
                    ],
                    'specialty' => $specialty
                ],
                'backstory' => $backstory
            ];

            if (file_put_contents($new_filename, json_encode($char_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                $query_params['message'] = 'บันทึกสายรหัส DNA ตัวละคร AI เรียบร้อยแล้ว!';
                if (isset($_SESSION['character']) && isset($_SESSION['character']['name']) && $_SESSION['character']['name'] === $nickname) {
                    $_SESSION['character'] = $char_data;
                }
            } else {
                $query_params['error'] = 'เกิดข้อผิดพลาดระดับไฟล์ระบบ ไม่สามารถเขียนข้อมูลตัวละคร AI ได้';
            }
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_character') {
        $filename = trim($_POST['filename'] ?? '');
        if (file_exists($filename)) {
            $data = json_decode(file_get_contents($filename), true);
            if (isset($data['avatar']) && file_exists($data['avatar']) && strpos($data['avatar'], 'characters/avatars/') !== false) {
                unlink($data['avatar']);
            }
            $identity_file = 'identity/' . basename($filename);
            if (file_exists($identity_file)) {
                unlink($identity_file);
            }
            if (unlink($filename)) {
                $query_params['message'] = 'ลบตัวละครเรียบร้อยแล้ว';
            } else {
                $query_params['error'] = 'ไม่สามารถลบไฟล์ตัวละครได้';
            }
        } else {
            $query_params['error'] = 'ไม่พบไฟล์ตัวละครที่ต้องการลบ';
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'reset_identity') {
        $identity_file = trim($_POST['identity_file'] ?? '');
        if (file_exists($identity_file)) {
            unlink($identity_file);
            $query_params['message'] = 'รีเซ็ตตัวตน (Identity) เรียบร้อยแล้ว';
        } else {
            $query_params['error'] = 'ไม่พบไฟล์ตัวตนที่ต้องการรีเซ็ต';
        }
    }

    if (!empty($query_params)) {
        $redirect_url .= '?' . http_build_query($query_params);
    }
    header('Location: ' . $redirect_url);
    exit;
}

 $message = $_GET['message'] ?? '';
 $error = $_GET['error'] ?? '';

 $user_profile = [];
if (file_exists('user_profile/user_dna.json')) {
    $user_profile = json_decode(file_get_contents('user_profile/user_dna.json'), true);
}

 $json_files = glob('characters/*.json');
 $has_characters = !empty($json_files);
?>
<!DOCTYPE html>
<html lang="th" class="h-full" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>AI Chat Settings - DNA Creator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
</head>
<body class="h-full flex flex-col font-sans bg-slate-50 dark:bg-[#0B0F19] text-slate-900 dark:text-slate-100">

    <header class="bg-white dark:bg-[#111827] border-b border-slate-200 dark:border-slate-800 p-4 flex-shrink-0 sticky top-0 z-20">
        <div class="max-w-7xl mx-auto flex items-center justify-between w-full gap-4">
            <div class="flex items-center gap-3">
                <button id="theme-toggle" class="p-2 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 flex-shrink-0">
                    <span class="dark:hidden text-sm">🌙</span>
                    <span class="hidden dark:inline text-sm">☀️</span>
                </button>
                <div class="flex items-center gap-2">
                    <div class="w-9 h-9 rounded bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-lg">🧬</div>
                    <div>
                        <h1 class="text-base sm:text-lg font-bold leading-tight">AI Chat Control Panel</h1>
                        <p class="text-[10px] sm:text-xs text-slate-400 dark:text-slate-500 font-medium hidden sm:block">ศูนย์บัญชาการแก้ไขรหัสพันธุกรรมและอัตลักษณ์</p>
                    </div>
                </div>
            </div>
            
            <a href="index.php" class="bg-[#0084FF] hover:bg-blue-600 text-white font-semibold text-xs sm:text-sm px-4 sm:px-5 py-2 sm:py-2.5 rounded-lg flex items-center gap-1.5 flex-shrink-0">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"></path>
                </svg>
                กลับแชท
            </a>
        </div>
    </header>

    <main class="flex-1 max-w-5xl w-full mx-auto p-4 sm:p-6 overflow-y-auto space-y-6 sm:space-y-8">
        
        <?php if (!empty($message)): ?>
            <div class="bg-green-50 dark:bg-green-950/50 border border-green-200 dark:border-green-900 text-green-700 dark:text-green-300 p-4 rounded-xl text-sm font-semibold">
                ✨ <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300 p-4 rounded-xl text-sm font-semibold">
                ⚠️ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-5 sm:p-6">
                    <div class="flex items-center gap-2 pb-4 mb-4 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-xl">👤</span>
                        <h3 class="font-bold text-sm text-slate-800 dark:text-slate-200">ข้อมูลของคุณ (User Settings)</h3>
                    </div>

                    <form method="POST" action="settings.php" enctype="multipart/form-data" class="space-y-4 text-xs sm:text-sm">
                        <input type="hidden" name="action" value="save_user_profile">
                        
                        <div>
                            <label class="block font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Gemini API Key</label>
                            <input type="password" name="api_key" placeholder="AI Key จาก Google API" 
                                   value="<?php echo htmlspecialchars($user_profile['api_key'] ?? ''); ?>"
                                   class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500" required>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">รูปประจำตัวของคุณ (Avatar)</label>
                            <input type="file" name="user_avatar" id="user_avatar_input" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-400 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1.5 text-[10px] sm:text-xs focus:outline-none">
                            <div id="user_avatar_preview_container" class="mt-2 <?php echo empty($user_profile['avatar']) ? 'hidden' : ''; ?> flex justify-center bg-slate-100 dark:bg-slate-800 rounded-lg p-2">
                                <img id="user_avatar_preview_img" src="<?php echo htmlspecialchars($user_profile['avatar'] ?? ''); ?>" class="w-20 h-20 rounded-full object-cover border-2 border-white dark:border-slate-700 shadow-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">ชื่อจริง</label>
                                <input type="text" name="user_first_name" placeholder="ชื่อจริง" 
                                       value="<?php echo htmlspecialchars($user_profile['dna']['first_name'] ?? ''); ?>"
                                       class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 text-slate-800 dark:text-slate-100">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">ชื่อเล่น</label>
                                <input type="text" name="user_nickname" placeholder="ชื่อเล่น" required
                                       value="<?php echo htmlspecialchars($user_profile['dna']['nickname'] ?? ''); ?>"
                                       class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 text-slate-800 dark:text-slate-100">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">เพศ</label>
                                <select name="user_gender" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-2 text-slate-800 dark:text-slate-100">
                                    <option value="ชาย" <?php echo isset($user_profile['dna']['gender']) && $user_profile['dna']['gender'] === 'ชาย' ? 'selected' : ''; ?>>ชาย (ครับ)</option>
                                    <option value="หญิง" <?php echo isset($user_profile['dna']['gender']) && $user_profile['dna']['gender'] === 'หญิง' ? 'selected' : ''; ?>>หญิง (ค่ะ)</option>
                                    <option value="อื่นๆ" <?php echo isset($user_profile['dna']['gender']) && $user_profile['dna']['gender'] === 'อื่นๆ' ? 'selected' : ''; ?>>อื่นๆ</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">วันเกิด</label>
                                <input type="date" name="user_birthdate" value="<?php echo htmlspecialchars($user_profile['dna']['birthdate'] ?? ''); ?>"
                                       class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1.5 text-slate-800 dark:text-slate-100">
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">บุคลิก/นิสัย</label>
                            <input type="text" name="user_personality" placeholder="ใจร้อน, อ่อนโยน" value="<?php echo htmlspecialchars($user_profile['dna']['personality'] ?? ''); ?>"
                                   class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 text-slate-800 dark:text-slate-100">
                        </div>

                        <div class="grid grid-cols-3 gap-1">
                            <div>
                                <label class="block font-bold text-slate-400 uppercase mb-0.5">สีตา</label>
                                <input type="text" name="user_eye_color" placeholder="ดำ" value="<?php echo htmlspecialchars($user_profile['dna']['eye_color'] ?? ''); ?>" class="w-full bg-slate-50 dark:bg-slate-700 rounded px-2 py-1.5 text-slate-800 dark:text-slate-100">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-400 uppercase mb-0.5">สีผม</label>
                                <input type="text" name="user_hair_color" placeholder="ดำ" value="<?php echo htmlspecialchars($user_profile['dna']['hair_color'] ?? ''); ?>" class="w-full bg-slate-50 dark:bg-slate-700 rounded px-2 py-1.5 text-slate-800 dark:text-slate-100">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-400 uppercase mb-0.5">สีผิว</label>
                                <input type="text" name="user_skin_color" placeholder="ขาว" value="<?php echo htmlspecialchars($user_profile['dna']['skin_color'] ?? ''); ?>" class="w-full bg-slate-50 dark:bg-slate-700 rounded px-2 py-1.5 text-slate-800 dark:text-slate-100">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-bold text-slate-400 uppercase mb-1">ฮอร์โมนชาย (%)</label>
                                <input type="number" name="user_hormone_male" min="1" max="99" value="<?php echo htmlspecialchars($user_profile['dna']['hormones']['male'] ?? 50); ?>" class="w-full bg-slate-50 dark:bg-slate-700 rounded px-2 py-1.5 text-slate-800 dark:text-slate-100">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-400 uppercase mb-1">ฮอร์โมนหญิง (%)</label>
                                <input type="number" name="user_hormone_female" min="1" max="99" value="<?php echo htmlspecialchars($user_profile['dna']['hormones']['female'] ?? 50); ?>" class="w-full bg-slate-50 dark:bg-slate-700 rounded px-2 py-1.5 text-slate-800 dark:text-slate-100">
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">โรคประจำตัว/สิ่งที่กลัว</label>
                            <input type="text" name="user_medical_conditions" placeholder="หอบหืด, กลัวเสียงฟ้าร้อง" value="<?php echo htmlspecialchars($user_profile['dna']['medical_conditions'] ?? ''); ?>"
                                   class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 text-slate-800 dark:text-slate-100">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">ความถนัดพิเศษ</label>
                            <input type="text" name="user_specialty" placeholder="วิศวกรรมไอที, แพทย์ศาสตร์" value="<?php echo htmlspecialchars($user_profile['dna']['specialty'] ?? ''); ?>"
                                   class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 text-slate-800 dark:text-slate-100">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">ประวัติเหตุการณ์ในอดีต</label>
                            <textarea name="user_backstory" rows="3" placeholder="อดีตที่หล่อหลอมตัวตน..." 
                                      class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500"><?php echo htmlspecialchars($user_profile['backstory'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-2.5 rounded-lg transition duration-200 text-center text-sm">
                            🧬 อัปเดต DNA ของคุณ
                        </button>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-6">
                
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-sm font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider hidden sm:block">สายรหัสพันธุ์กรรม AI</h2>
                    <button id="show-form-btn" onclick="openCreateForm()" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs sm:text-sm px-4 py-2 rounded-lg flex items-center gap-1.5 shadow-sm ml-auto">
                        <span>🪄</span> สร้างตัวละครใหม่
                    </button>
                </div>

                <div id="no-character-view" class="<?php echo $has_characters ? 'hidden' : ''; ?> bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-2xl p-8 sm:p-12 text-center">
                    <div class="w-16 h-16 bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 rounded-full flex items-center justify-center text-2xl mx-auto mb-4">📂</div>
                    <h3 class="text-base font-bold text-slate-700 dark:text-slate-300">ยังไม่มีข้อมูลตัวตนในโฟลเดอร์</h3>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1 max-w-xs mx-auto">ในระบบโฟลเดอร์ characters ยังว่างเปล่าอย่างสมบูรณ์แบบ</p>
                    <button id="no-char-create-btn" onclick="openCreateForm()" class="mt-4 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs px-4 py-2 rounded-lg inline-flex items-center gap-1.5 shadow-sm">
                        <span>🪄</span> ปั้นตัวละครคนแรกเลย
                    </button>
                </div>

                <div id="character-list-view" class="<?php echo !$has_characters ? 'hidden' : ''; ?> grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($json_files as $file): ?>
                        <?php 
                        $data = json_decode(file_get_contents($file), true);
                        if ($data):
                            $identity_file = 'identity/' . basename($file);
                            $identity = file_exists($identity_file) ? json_decode(file_get_contents($identity_file), true) : null;
                            $data['identity'] = $identity;
                            $json_data_attr = htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="bg-white dark:bg-[#111827] border border-slate-200 dark:border-slate-800 rounded-xl p-4 flex justify-between items-start shadow-sm hover:border-blue-400 dark:hover:border-blue-600 transition-colors gap-2">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <?php if (!empty($data['avatar'])): ?>
                                    <img src="<?php echo htmlspecialchars($data['avatar']); ?>" class="w-12 h-12 rounded-full object-cover border border-slate-200 dark:border-slate-700 flex-shrink-0">
                                <?php else: ?>
                                    <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 flex items-center justify-center font-bold text-lg flex-shrink-0">
                                        <?php echo htmlspecialchars(mb_substr($data['name'], 0, 1, 'UTF-8')); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="min-w-0 flex-1">
                                    <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm truncate"><?php echo htmlspecialchars($data['name']); ?></h4>
                                    <p class="text-[10px] text-slate-400 dark:text-slate-500 truncate">เชี่ยวชาญ: <?php echo htmlspecialchars($data['dna']['specialty'] ?? 'ทั่วไป'); ?></p>
                                    <?php if ($identity): ?>
                                        <div class="mt-1 flex flex-wrap gap-x-2 text-[10px] font-medium text-amber-500 dark:text-amber-400">
                                            <span>🧠 <?php echo htmlspecialchars($identity['mood'] ?? '-'); ?></span>
                                            <span>❤️ <?php echo htmlspecialchars($identity['intimacy'] ?? 0); ?>%</span>
                                            <span>🤝 <?php echo htmlspecialchars($identity['trust'] ?? 0); ?>%</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-1 text-[10px] text-slate-400">ยังไม่มีตัวตนเกิดขึ้น</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex flex-col gap-1 flex-shrink-0">
                                <button onclick="openEditForm('<?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>', <?php echo $json_data_attr; ?>)" class="text-[10px] bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/30 dark:hover:bg-blue-800/50 text-blue-600 dark:text-blue-400 border border-blue-100 dark:border-blue-800 rounded px-2 py-1.5 font-bold transition">
                                    แก้ไข
                                </button>
                                <?php if ($identity): ?>
                                <form method="POST" action="settings.php" onsubmit="return confirm('ยืนยันการรีเซ็ตตัวตน (Identity) ของตัวละครนี้? ค่าอารมณ์และความสัมพันธ์จะกลับเป็นศูนย์');" class="inline">
                                    <input type="hidden" name="action" value="reset_identity">
                                    <input type="hidden" name="identity_file" value="<?php echo htmlspecialchars($identity_file, ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" class="w-full text-[10px] bg-amber-50 hover:bg-amber-100 dark:bg-amber-900/30 dark:hover:bg-amber-800/50 text-amber-600 dark:text-amber-400 border border-amber-100 dark:border-amber-800 rounded px-2 py-1.5 font-bold transition">
                                        รีเซ็ต
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" action="settings.php" onsubmit="return confirm('ยืนยันการลบตัวละครนี้? ข้อมูลจะหายไปถาวร');" class="inline">
                                    <input type="hidden" name="action" value="delete_character">
                                    <input type="hidden" name="filename" value="<?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" class="w-full text-[10px] bg-rose-50 hover:bg-rose-100 dark:bg-rose-900/30 dark:hover:bg-rose-800/50 text-rose-600 dark:text-rose-400 border border-rose-100 dark:border-rose-800 rounded px-2 py-1.5 font-bold transition">
                                        ลบ
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div id="creator-form-container" class="hidden bg-white dark:bg-[#111827] border border-blue-200 dark:border-blue-900 rounded-2xl p-5 sm:p-6 shadow-md transition-all duration-300">
                    <div class="border-b border-slate-100 dark:border-slate-800 pb-3 mb-4 flex justify-between items-center">
                        <h3 id="form-title-text" class="font-bold text-sm text-blue-700 dark:text-blue-400">สร้างตัวละคร AI (Create DNA)</h3>
                        <button onclick="closeForm()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-xs font-bold bg-slate-50 dark:bg-slate-800 px-2 py-1 rounded">ยกเลิก ✕</button>
                    </div>

                    <form method="POST" action="settings.php" enctype="multipart/form-data" class="space-y-4 text-xs sm:text-sm" id="ai-dna-form">
                        <input type="hidden" name="action" value="save_character">
                        <input type="hidden" name="old_filename" id="form-old-filename" value="">
                        <input type="hidden" name="existing_avatar" id="form-existing-avatar" value="">

                        <div id="identity-section" class="hidden border-l-4 border-amber-500 pl-3 mb-2 pb-2 border-b border-slate-100 dark:border-slate-800">
                            <label class="block font-bold text-amber-500 uppercase tracking-wider mb-2 text-[10px]">ตัวตนที่มีอยู่ (Identity - อัปเดตอัตโนมัติ)</label>
                            <div id="identity-display" class="text-xs text-slate-500 dark:text-slate-400 mt-1 space-y-2"></div>
                        </div>

                        <div class="border-l-4 border-rose-500 pl-3 mb-2 pb-2 border-b border-slate-100 dark:border-slate-800">
                            <label class="block font-bold text-rose-500 uppercase tracking-wider mb-2 text-[10px]">อัตลักษณ์ทางรูปภาพ (AI Avatar Profile)</label>
                            <div class="flex items-center gap-4">
                                <div id="avatar-preview-container" class="hidden">
                                    <img id="avatar-preview-img" src="" class="w-14 h-14 rounded-full object-cover border-2 border-slate-200 dark:border-slate-700">
                                </div>
                                <div class="flex-1">
                                    <input type="file" name="ai_avatar" id="ai_avatar_input" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-500 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1.5 text-[10px] focus:outline-none">
                                    <p class="text-[9px] text-slate-400 mt-1">อัปโหลดรูปภาพใหม่เพื่อเปลี่ยนหน้าตาของ AI</p>
                                </div>
                            </div>
                        </div>

                        <div class="border-l-4 border-blue-500 pl-2 mb-2 mt-4">
                            <span class="text-[10px] font-bold text-blue-500 uppercase tracking-wider">รหัสชีวภาพภายนอก (Physical DNA)</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block font-bold text-slate-400 uppercase mb-1">ชื่อจริง</label>
                                <input type="text" name="first_name" id="form-first-name" placeholder="เช่น Kaoru" class="w-full bg-slate-50 dark:bg-slate-700 rounded px-3 py-1.5 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500" required>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-400 uppercase mb-1">ชื่อเล่น (ใช้สำหรับตั้งชื่อไฟล์)</label>
                                <input type="text" name="nickname" id="form-nickname" placeholder="เช่น คาโอรุ" class="w-full bg-slate-50 dark:bg-slate-700 rounded px-3 py-1.5 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500 border-l-2 border-blue-400" required>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-400 uppercase mb-1">นามสกุล</label>
                                <input type="text" name="last_name" id="form-last-name" placeholder="เช่น Misogiya" class="w-full bg-slate-50 dark:bg-slate-700 rounded px-3 py-1.5 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-400 uppercase mb-1">เพศ</label>
                                <select name="gender" id="form-gender" class="w-full bg-slate-50 dark:bg-slate-700 rounded px-2 py-1.5 text-slate-800 dark:text-slate-100 focus:outline-none">
                                    <option value="หญิง">หญิง (คะ/ค่ะ/จ๋า)</option>
                                    <option value="ชาย">ชาย (ครับ/ฮะ/ผม)</option>
                                    <option value="อื่นๆ">อื่นๆ</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-400 uppercase mb-1">วันเดือนปีเกิด (ค.ศ.)</label>
                                <input type="date" name="birthdate" id="form-birthdate" class="w-full bg-slate-50 dark:bg-slate-700 rounded px-2 py-1.5 text-slate-800 dark:text-slate-100 focus:outline-none" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block font-bold text-slate-400 uppercase mb-1">สีตา</label>
                                <input type="text" name="eye_color" id="form-eye-color" placeholder="สีตา" class="w-full bg-slate-50 dark:bg-slate-700 rounded px-3 py-1.5 text-slate-800 dark:text-slate-100 focus:outline-none">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-400 uppercase mb-1">สีผม</label>
                                <input type="text" name="hair_color" id="form-hair-color" placeholder="สีผม" class="w-full bg-slate-50 dark:bg-slate-700 rounded px-3 py-1.5 text-slate-800 dark:text-slate-100 focus:outline-none">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-400 uppercase mb-1">สีผิว</label>
                                <input type="text" name="skin_color" id="form-skin-color" placeholder="สีผิว" class="w-full bg-slate-50 dark:bg-slate-700 rounded px-3 py-1.5 text-slate-800 dark:text-slate-100 focus:outline-none">
                            </div>
                        </div>

                        <div class="border-l-4 border-emerald-500 pl-2 mb-2">
                            <span class="text-[10px] font-bold text-emerald-500 uppercase tracking-wider">ระบบฮอร์โมน (Internal Biological Chemistry)</span>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-400 uppercase mb-1">ฮอร์โมนชาย (1-99)</label>
                                <input type="number" name="hormone_male" id="form-hormone-male" min="1" max="99" value="50" class="w-full bg-slate-50 dark:bg-slate-700 rounded px-3 py-1.5 text-slate-800 dark:text-slate-100 focus:outline-none" required>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-400 uppercase mb-1">ฮอร์โมนหญิง (1-99)</label>
                                <input type="number" name="hormone_female" id="form-hormone-female" min="1" max="99" value="50" class="w-full bg-slate-50 dark:bg-slate-700 rounded px-3 py-1.5 text-slate-800 dark:text-slate-100 focus:outline-none" required>
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-400 uppercase mb-1">โรคประจำตัว / ข้อจำกัด / สิ่งที่กลัว</label>
                            <input type="text" name="medical_conditions" id="form-medical-conditions" placeholder="เช่น หอบหืด, แพ้ถั่วลิสง" class="w-full bg-slate-50 dark:bg-slate-700 rounded px-3 py-1.5 text-slate-800 dark:text-slate-100 focus:outline-none">
                        </div>

                        <div class="border-l-4 border-purple-500 pl-2 mb-2">
                            <span class="text-[10px] font-bold text-purple-500 uppercase tracking-wider">บุคลิกและความสามารถ (Talent & Mind)</span>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-400 uppercase mb-1">ความสามารถเฉพาะทาง</label>
                            <textarea name="specialty" id="form-specialty" rows="2" placeholder="เช่น ทักษะการพัฒนาโปรแกรมภาษา Rust ขั้นสูง" class="w-full bg-slate-50 dark:bg-slate-700 rounded px-3 py-1.5 text-slate-800 dark:text-slate-100 focus:outline-none"></textarea>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-400 uppercase mb-1">ลักษณะอุปนิสัย</label>
                            <input type="text" name="personality" id="form-personality" placeholder="ปากร้ายแต่ใจดี, ขี้เล่นแต่อ่อนโยน" class="w-full bg-slate-50 dark:bg-slate-700 rounded px-3 py-1.5 text-slate-800 dark:text-slate-100 focus:outline-none" required>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-400 uppercase mb-1">ประวัติชีวิตและความขัดแย้ง (Backstory)</label>
                            <textarea name="backstory" id="form-backstory" rows="4" placeholder="ปูมหลังอันขมขื่นเพื่อประกอบความตระหนักรู้ร่วมกับ DNA..." class="w-full bg-slate-50 dark:bg-slate-700 rounded px-3 py-1.5 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500" required></textarea>
                        </div>

                        <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                            <button type="submit" id="form-submit-btn" class="bg-blue-600 hover:bg-blue-500 text-white font-bold py-2.5 px-6 rounded-xl text-xs shadow-md transition-all">
                                🪐 สถาปนารหัสพันธุกรรม
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        const themeToggle = document.getElementById('theme-toggle');
        const htmlRoot = document.getElementById('html-root');

        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            htmlRoot.classList.add('dark');
        } else {
            htmlRoot.classList.remove('dark');
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

        const creatorForm = document.getElementById('creator-form-container');
        const listContainer = document.getElementById('character-list-view');
        const noCharView = document.getElementById('no-character-view');

        function openCreateForm() {
            document.getElementById('ai-dna-form').reset();
            document.getElementById('form-old-filename').value = '';
            document.getElementById('form-existing-avatar').value = '';
            document.getElementById('form-title-text').innerText = 'สร้างตัวละคร AI (Create DNA)';
            document.getElementById('form-submit-btn').innerText = '🪐 สถาปนารหัสพันธุกรรมใหม่';
            document.getElementById('form-submit-btn').classList.replace('bg-emerald-600', 'bg-blue-600');
            document.getElementById('form-submit-btn').classList.replace('hover:bg-emerald-500', 'hover:bg-blue-500');
            document.getElementById('avatar-preview-container').classList.add('hidden');
            document.getElementById('identity-section').classList.add('hidden');
            
            creatorForm.classList.remove('hidden');
            if (listContainer) listContainer.classList.add('hidden');
            if (noCharView) noCharView.classList.add('hidden');
            window.scrollTo({ top: creatorForm.offsetTop - 80, behavior: 'smooth' });
        }

        function openEditForm(filename, data) {
            document.getElementById('form-old-filename').value = filename;
            document.getElementById('form-existing-avatar').value = data.avatar || '';
            document.getElementById('form-first-name').value = data.dna.first_name || '';
            document.getElementById('form-nickname').value = data.dna.nickname || '';
            document.getElementById('form-last-name').value = data.dna.last_name || '';
            document.getElementById('form-gender').value = data.dna.gender || 'หญิง';
            document.getElementById('form-birthdate').value = data.dna.birthdate || '';
            document.getElementById('form-eye-color').value = data.dna.eye_color || '';
            document.getElementById('form-hair-color').value = data.dna.hair_color || '';
            document.getElementById('form-skin-color').value = data.dna.skin_color || '';
            document.getElementById('form-hormone-male').value = data.dna.hormones.male || 50;
            document.getElementById('form-hormone-female').value = data.dna.hormones.female || 50;
            document.getElementById('form-medical-conditions').value = data.dna.medical_conditions || '';
            document.getElementById('form-specialty').value = data.dna.specialty || '';
            document.getElementById('form-personality').value = data.dna.personality || '';
            document.getElementById('form-backstory').value = data.backstory || '';

            if (data.avatar) {
                document.getElementById('avatar-preview-img').src = data.avatar;
                document.getElementById('avatar-preview-container').classList.remove('hidden');
            } else {
                document.getElementById('avatar-preview-container').classList.add('hidden');
            }

            if (data.identity) {
                let idHtml = '<div class="p-2 bg-slate-50 dark:bg-slate-700 rounded"><b>อารมณ์:</b> ' + (data.identity.mood || '-') + '</div>';
                idHtml += '<div class="p-2 bg-slate-50 dark:bg-slate-700 rounded"><b>ความคิดในใจ:</b> ' + (data.identity.thought || '-') + '</div>';
                
                const bars = [
                    {label: 'ความสนิทสนม', val: data.identity.intimacy || 0, color: 'bg-emerald-500'},
                    {label: 'ความไว้วางใจ', val: data.identity.trust || 0, color: 'bg-blue-500'},
                    {label: 'ความรู้สึกทางโรแมนติก', val: data.identity.affection || 0, color: 'bg-rose-500'},
                    {label: 'ความเครียด', val: data.identity.stress || 0, color: 'bg-amber-500'},
                    {label: 'พลังงาน', val: data.identity.energy || 0, color: 'bg-indigo-500'}
                ];
                
                bars.forEach(b => {
                    const v = b.val > 100 ? 100 : (b.val < 0 ? 0 : b.val);
                    idHtml += `
                        <div>
                            <div class="flex justify-between mb-1 text-[10px]"><span>${b.label}</span><span>${v}%</span></div>
                            <div class="w-full bg-slate-200 dark:bg-slate-900 rounded-full h-1.5">
                                <div class="${b.color} h-1.5 rounded-full" style="width: ${v}%"></div>
                            </div>
                        </div>
                    `;
                });
                
                document.getElementById('identity-display').innerHTML = idHtml;
                document.getElementById('identity-section').classList.remove('hidden');
            } else {
                document.getElementById('identity-section').classList.add('hidden');
            }

            document.getElementById('form-title-text').innerText = 'แก้ไข DNA: ' + (data.dna.nickname || '');
            document.getElementById('form-submit-btn').innerText = '🧬 บันทึกการแก้ไข DNA';
            document.getElementById('form-submit-btn').classList.replace('bg-blue-600', 'bg-emerald-600');
            document.getElementById('form-submit-btn').classList.replace('hover:bg-blue-500', 'hover:bg-emerald-500');

            creatorForm.classList.remove('hidden');
            if (listContainer) listContainer.classList.add('hidden');
            if (noCharView) noCharView.classList.add('hidden');
            
            window.scrollTo({ top: creatorForm.offsetTop - 80, behavior: 'smooth' });
        }

        function closeForm() {
            creatorForm.classList.add('hidden');
            if (listContainer && <?php echo $has_characters ? 'true' : 'false'; ?>) {
                listContainer.classList.remove('hidden');
            } else if (noCharView) {
                noCharView.classList.remove('hidden');
            }
        }

        const userAvatarInput = document.getElementById('user_avatar_input');
        const userAvatarPreviewImg = document.getElementById('user_avatar_preview_img');
        const userAvatarPreviewContainer = document.getElementById('user_avatar_preview_container');

        userAvatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    userAvatarPreviewImg.src = event.target.result;
                    userAvatarPreviewContainer.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        });

        const aiAvatarInput = document.getElementById('ai_avatar_input');
        const aiAvatarPreviewImg = document.getElementById('avatar-preview-img');
        const aiAvatarPreviewContainer = document.getElementById('avatar-preview-container');

        if (aiAvatarInput) {
            aiAvatarInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        aiAvatarPreviewImg.src = event.target.result;
                        aiAvatarPreviewContainer.classList.remove('hidden');
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    </script>
</body>
</html>