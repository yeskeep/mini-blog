<?php
/**
 * 极简自适应论坛 - 多平台视频支持版
 * 1. 默认首页列出文章全文
 * 2. 编辑器插入图片去掉了默认的 http://
 * 3. 支持 YouTube 和 Bilibili 短代码解析
 */

$adminUser = 'admin';
$adminPass = '123456';
$cookieName = 'forum_admin_auth';
$authValue = md5($adminUser . $adminPass . 'salt123');

if (isset($_POST['login'])) {
    if ($_POST['user'] === $adminUser && $_POST['pass'] === $adminPass) {
        setcookie($cookieName, $authValue, time() + 3600 * 24, "/");
        header("Location: index.php"); exit;
    } else { $error = "账号或密码错误！"; }
}

if (isset($_GET['logout'])) {
    setcookie($cookieName, '', time() - 3600, "/");
    header("Location: index.php"); exit;
}

$isAdmin = isset($_COOKIE[$cookieName]) && $_COOKIE[$cookieName] === $authValue;
$postDir = 'posts/';
if (!is_dir($postDir)) mkdir($postDir, 0777, true);

function getPostTitle($path, $default = '无标题') {
    if (!file_exists($path)) return $default;
    $f = fopen($path, 'r');
    $line = fgets($f);
    fclose($f);
    return trim(str_replace('#', '', $line)) ?: $default;
}

if ((isset($_GET['delete']) || isset($_GET['edit']) || (isset($_POST['content']) && !isset($_POST['login']))) && !$isAdmin) {
    $showLogin = true;
}

if (isset($_GET['delete']) && $isAdmin) {
    // 关键修改：使用 basename 过滤
    $fileToDelete = basename($_GET['delete']); 
    
    if (file_exists($postDir . $fileToDelete) && $fileToDelete !== '.' && $fileToDelete !== '..') { 
        unlink($postDir . $fileToDelete); 
    }
    header("Location: index.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content']) && $isAdmin) {
    // 关键修改：对已有文件名进行 basename 过滤
    $filename = (!empty($_POST['existing_file'])) ? basename($_POST['existing_file']) : substr(md5(uniqid(mt_rand(), true)), 0, 8) . '.md';
    
    // 确保文件名始终以 .md 结尾（可选增强）
    if (pathinfo($filename, PATHINFO_EXTENSION) !== 'md') {
        $filename = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $filename);
    }

    $finalData = "# " . trim($_POST['title']) . "\n\n" . trim($_POST['content']);
    file_put_contents($postDir . $filename, $finalData);
    header("Location: index.php?view=" . urlencode($filename)); exit;
}

$viewFile = isset($_GET['view']) ? basename($_GET['view']) : '';
$editFile = isset($_GET['edit']) ? basename($_GET['edit']) : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jackie's Notes</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/simplemde/latest/simplemde.min.css">
    <script src="https://cdn.jsdelivr.net/simplemde/latest/simplemde.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
 .footer {
    background: white;
    color: #999;
    text-align: center; /* 确保文字居中 */
    padding: 30px 20px;
    font-size: 13px;
    border-top: 1px solid #eee;
    margin-top: auto;
    width: 100%;        /* 确保撑满宽度 */
    box-sizing: border-box;
}

/* 确保管理员链接所在的 div 也是居中的 */
.admin-zone, .footer div {
    text-align: center;
    margin: 0 auto;
}
        :root { --primary: #2c3e50; --accent: #005bb7; --bg: #f4f7f6; }
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; background: #eef2f3; color: #333; }
        .wrapper { display: flex; flex-direction: column; min-height: 100vh; }
        .container { display: flex; flex: 1; flex-direction: row-reverse; max-width: 1400px; margin: 0 auto; width: 100%; }
        @media (max-width: 768px) { .container { flex-direction: column-reverse; } .top-banner { padding: 30px 20px; } .top-banner h1 { font-size: 1.8rem; } }
        .top-banner { background: var(--accent); color: white; padding: 60px 20px; text-align: center; position: relative; }
        .top-banner h1 { margin: 0; font-size: 2.8rem; font-weight: 300; letter-spacing: 3px; }
/* 1. 导航容器：设置与下方主体相同的 max-width 并居中 */
.nav-container {
    max-width: 1400px; /* 必须与 .container 的 max-width 一致 */
    margin: 0 auto;
    position: relative;
    height: 0; /* 不占高度，防止影响标题位置 */
}

/* 2. 导航链接：去掉 top:15px 和 left:20px 的绝对定位 */
.top-nav {
    position: absolute;
    top: -45px; /* 根据你的 banner 高度向上微调，建议在 40-50px 左右 */
    left: 25px; /* 这里的 25px 对应你下方 .main-content 的 padding-left */
    font-size: 13px;
    opacity: 0.8;
}
        .top-nav a { color: white; text-decoration: none; font-weight: bold; }
/* 1. 修改侧边栏容器：去掉白色背景和边框线 */
.sidebar {
    width: 300px;
    background: transparent; /* 改为透明，让整体背景色透过来 */
    border: none;            /* 去掉右侧那条多余的竖线 */
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    padding-top: 20px;       /* 保持 20px 的顶部对齐间距 */
}

/* 2. 优化 RECENT POSTS 区域：把它做成独立的小卡片感 */
.sidebar .post-list {
    background: #fdfdfd;      /* 给列表框增加和文章一样的米白色 */
    margin: 0 15px 25px 15px; /* 与 Banner 和边缘保持距离 */
    padding: 20px;
    border-radius: 8px;       /* 统一的圆角 */
    border: 1px solid #eee;   /* 淡淡的边框 */
    box-shadow: 0 1px 3px rgba(0,0,0,0.03); /* 淡淡的阴影 */
}

/* 3. 确保主内容区顶部也是 20px，实现绝对对齐 */
.main-content {
    flex: 1;
    padding: 20px 25px 25px 25px; /* 顶部设为 20px */
    background: transparent;
    box-sizing: border-box;
}
        @media (max-width: 768px) {

    .sidebar {
        width: 100%;
        padding: 0 ;   /* ✅ 加这个 */
        box-sizing: border-box;
    }

}
        .sidebar-header { padding: 15px; border-bottom: 1px solid #eee; }
        .new-btn { display: block; padding: 12px; background: var(--accent); color: white; text-align: center; text-decoration: none; border-radius: 6px; font-weight: bold; }
        .login-entry { display: block; padding: 10px; background: #95a5a6; color: white; text-align: center; text-decoration: none; border-radius: 6px; font-size: 14px; }
        .post-list { flex: 1; overflow-y: auto; padding: 25px 15px;}
/* 优化列表项布局 */
.post-item {
    display: flex;
    justify-content: space-between; /* 确保内容两端对齐 */
    align-items: center;
    padding: 10px 15px;
    margin-bottom: 8px;
    border-radius: 6px;
    background: #f5f5f5;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}

/* 标题链接：强制占满左侧空间 */
.post-item a:first-child {
    text-decoration: none;
    color: #555;
    font-size: 14px;
    flex: 1;            /* 关键：让标题占据左侧所有空间 */
    margin-right: 15px; /* 给右侧删除按钮留出间距 */
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* 删除按钮样式优化 */
.post-item .del-link {
    color: #ccc;        /* 默认颜色淡一点，不抢眼 */
    text-decoration: none;
    font-size: 18px;    /* 稍微大一点方便点 */
    font-weight: bold;
    padding: 0 5px;
    flex-shrink: 0;     /* 防止按钮被挤压 */
    transition: color 0.2s;
}

.post-item .del-link:hover {
    color: #e74c3c;    /* 鼠标悬停变红，提示危险操作 */
}
        




/* 重点在这里：为左侧列表中的每一篇文章卡片增加 Hexo 风格的灰色框 */
#posts-container .post-card {
    background: #fdfdfd; /* 比纯白稍微暗一点的米白色，更有质感 */
    padding: 25px; /* 卡片内部的边距 */
    margin-bottom: 20px; /* 卡片之间的间距 */
    border-radius: 8px; /* 圆角 */
    border: 1px solid #eee; /* 淡淡的灰色边框 */
    box-shadow: 0 1px 3px rgba(0,0,0,0.03); /* 极淡的阴影，增加立体感 */
}

@media (max-width: 768px) {
.top-nav {
        left: 15px; /* 对应手机端容器的 padding */
    }
    /* 1. 统一左右边距来源 */
    .container {
        padding: 0 15px;
        box-sizing: border-box;
    }

    /* 2. 去掉 sidebar 自己的边距 */
    .post-list {
        margin: 0 0 20px 0 !important;
    }

    /* 3. main-content 不再单独控制左右 */
    .main-content {
        padding: 20px 0;
    }
}
        .post-card { margin-bottom: 60px; padding-bottom: 40px; border-bottom: 1px solid #eee; }
        .post-card h2 { font-size: 1.8rem; margin-top: 0; }
        .post-card h2 a { text-decoration: none; color: #333; }
        .login-box { max-width: 320px; margin: 40px auto; padding: 30px; border: 1px solid #eee; border-radius: 12px; background: #fff; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .pass-wrapper { position: relative; margin-bottom: 15px; }
        .pass-wrapper input { width: 100%; padding: 12px; padding-right: 40px; box-sizing: border-box; border: 1px solid #ddd; border-radius: 6px; }
        .toggle-pass { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #999; font-size: 18px; user-select: none; }
        /* 视频容器保持 16:9 */
        .video-container { position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; margin: 25px 0; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); background: #000; }
        .video-container iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; }
        .CodeMirror { height: 500px; border-radius: 6px; }
        .markdown-body { line-height: 1.8; font-size: 16px; }
        .markdown-body img { max-width: 100%; border-radius: 8px; }
        /* 行内代码样式 */
/* 行内代码样式：针对反引号包围的内容 */
.markdown-body code {
    padding: 2px 6px;
    margin: 0 4px;
    background-color: rgba(27, 31, 35, 0.05); /* 淡淡的灰色背景 */
    color: #e74c3c; /* 使用醒目的暗红色 */
    border-radius: 3px;
    font-family: ui-monospace, SFMono-Regular, SF Mono, Menlo, Consolas, Liberation Mono, monospace;
    font-size: 0.9em;
}

/* 多行代码块样式：针对三个反引号包围的内容 */
.markdown-body pre {
    padding: 16px;
    overflow: auto;
    font-size: 85%;
    line-height: 1.45;
    background-color: #f6f8fa; /* 浅灰背景 */
    border-radius: 6px;
    border: 1px solid #ddd;
    margin-bottom: 16px;
}

.markdown-body pre code {
    background-color: transparent; /* 块级代码不需要行内背景 */
    color: #333;
    padding: 0;
    margin: 0;
    font-size: 100%;
    word-break: normal;
    white-space: pre; /* 保持换行 */
}
    </style>
</head>
<body>

<div class="wrapper">
<header class="top-banner">
    <div class="nav-container">
        <nav class="top-nav"><a href="<?= $_SERVER['PHP_SELF'] ?>">HOME</a></nav>
    </div>
    <h1>Jackie</h1>
</header>

    <div class="container">
        <aside class="sidebar">
    <div class="post-list">
        <div style="font-size: 12px; color: #999; margin-bottom: 15px; padding-left: 5px; letter-spacing: 1px;">
            RECENT POSTS
        </div>
        <?php
        $allFiles = array_diff(scandir($postDir), array('.', '..', '.DS_Store'));
        if ($allFiles) {
            array_multisort(array_map('filemtime', array_map(fn($f) => $postDir.$f, $allFiles)), SORT_DESC, $allFiles);
            foreach ($allFiles as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) !== 'md') continue;
                $active = ($viewFile == $file || $editFile == $file) ? 'active' : '';
                $title = getPostTitle($postDir . $file, str_replace('.md', '', $file));
                echo "<div class='post-item $active'>";
                echo "<a href='?view=" . urlencode($file) . "'>" . htmlspecialchars($title) . "</a>";
                if ($isAdmin) echo "<a href='?delete=" . urlencode($file) . "' class='del-link' onclick='return window.confirm(\"确定要删除这篇文章吗？\");'>×</a>";
                echo "</div>";
            }
        }
        ?>
    </div>
</aside>

        <main class="main-content">
            <?php if (isset($showLogin) && !$isAdmin): ?>
                <div class="login-box">
                    <h3 style="text-align:center; font-weight:300;">验证身份</h3>
                    <?php if(isset($error)) echo "<p style='color:#e74c3c; text-align:center; font-size:13px;'>$error</p>"; ?>
                    <form method="POST">
                        <input type="text" name="user" style="width:100%; padding:12px; margin-bottom:15px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;" placeholder="账号" required autofocus>
                        <div class="pass-wrapper">
                            <input type="password" name="pass" id="login-pass" placeholder="密码" required>
                            <span class="toggle-pass" id="eye-btn">👁️</span>
                        </div>
                        <button type="submit" name="login" class="btn new-btn" style="width:100%; border:none; cursor:pointer;">登录</button>
                    </form>
                </div>
                <script>
                    document.getElementById('eye-btn').addEventListener('click', function() {
                        const p = document.getElementById('login-pass');
                        p.type = p.type === 'password' ? 'text' : 'password';
                        this.innerText = p.type === 'password' ? '👁️' : '👓';
                    });
                </script>

            <?php elseif ($editFile && $isAdmin): 
                $currentContent = '';
                if ($editFile !== 'new' && file_exists($postDir . $editFile)) {
                    $raw = file_get_contents($postDir . $editFile);
                    $lines = explode("\n", $raw);
                    if (strpos($lines[0], '#') === 0) { array_shift($lines); $currentContent = ltrim(implode("\n", $lines)); }
                    else { $currentContent = $raw; }
                }
            ?>
                <form method="POST">
                    <input type="text" name="title" style="width:100%; padding:12px; font-size:1.2rem; margin-bottom:15px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;" placeholder="文章标题" value="<?= htmlspecialchars(getPostTitle($postDir.$editFile, '')) ?>" required>
                    <input type="hidden" name="existing_file" value="<?= $editFile !== 'new' ? htmlspecialchars($editFile) : '' ?>">
                    <textarea id="editor" name="content"><?= htmlspecialchars($currentContent) ?></textarea>
                    <button type="submit" class="new-btn" style="width:100%; border:none; margin-top:15px; cursor:pointer; height:45px;">发布文章</button>
                </form>
                <script>
    // 初始化编辑器
    var simplemde = new SimpleMDE({ 
        element: document.getElementById("editor"), 
        spellChecker: false,
        autosave: {
            enabled: true,
            uniqueId: "jackie_notes_save", // 自动保存防止丢失
            delay: 2000,
        },
        insertTexts: { image: ["![](", ")"] }
    });

    var isDirty = false; 
    var isSubmitting = false; 

    // 1. 监控内容变化
    simplemde.codemirror.on("change", function(){
        isDirty = true;
    });
    document.querySelector('input[name="title"]').addEventListener('input', function() {
        isDirty = true;
    });

    // 2. 提交表单时标记为正在提交
    document.querySelector('form').addEventListener('submit', function() {
        isSubmitting = true;
    });

    // 3. 浏览器窗口/标签页关闭拦截 (PC端主要靠这个)
    window.addEventListener('beforeunload', function (e) {
        if (isDirty && !isSubmitting) {
            e.preventDefault();
            e.returnValue = '内容尚未保存，确定要离开吗？'; 
        }
    });

    // 4. 页面内链接点击拦截 (针对手机端误触和PC端点击导航栏)
    document.addEventListener('click', function(e) {
        // 寻找被点击的 A 标签
        var anchor = e.target.closest('a');
        
        // 如果点击的是链接，且内容已修改，且不是正在提交
        if (anchor && isDirty && !isSubmitting) {
            // --- 核心修复：排除编辑器工具栏 ---
            // 如果链接在编辑器工具栏 (editor-toolbar) 内部，则不拦截
            if (anchor.closest('.editor-toolbar')) {
                return; 
            }

            // 如果链接带有 javascript:void(0) 或类似伪链接，也不拦截
            if (anchor.getAttribute('href') && anchor.getAttribute('href').startsWith('javascript')) {
                return;
            }

            // 执行拦截
            if (!confirm("内容尚未保存，离开将丢失数据，确定吗？")) {
                e.preventDefault();
                e.stopImmediatePropagation();
            }
        }
    }, true); // 使用捕获模式确保优先执行
    // 这种写法能确保即便点到“×”的边缘也能触发
document.addEventListener('click', function(e) {
    // 寻找点击的目标是否为 del-link
    var delBtn = e.target.closest('.del-link');
    
    if (delBtn) {
        // 弹出确认框
        var sure = window.confirm("确定要永久删除这篇文章吗？");
        if (!sure) {
            // 如果点取消，彻底拦截所有后续动作
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    }
}, true); // 注意这里的 true，开启“捕获模式”，优先级最高
</script>

            <?php else: ?>
                <div id="posts-container">
                    <?php
                    $displayFiles = $viewFile ? [$viewFile] : array_slice($allFiles, 0, 5);
                    if (!$displayFiles) { echo "<div style='text-align:center; color:#ddd; margin-top:100px;'><h2>暂无文章</h2></div>"; }
                    foreach ($displayFiles as $file):
                        if (!file_exists($postDir . $file)) continue;
                        $raw = file_get_contents($postDir . $file);
                        $title = getPostTitle($postDir . $file);
                        $lines = explode("\n", $raw);
                        if (strpos($lines[0], '#') === 0) { array_shift($lines); $content = ltrim(implode("\n", $lines)); }
                        else { $content = $raw; }
                    ?>
                    <article class="post-card">
                        <h2><a href="?view=<?= urlencode($file) ?>"><?= htmlspecialchars($title) ?></a></h2>
                        <div class="markdown-body" data-raw="<?= htmlspecialchars($content) ?>"></div>
                        <?php if($isAdmin): ?>
                            <div style="margin-top:15px;"><a href="?edit=<?= urlencode($file) ?>" style="color:var(--accent); font-size:14px; text-decoration:none;">编辑此文 →</a></div>
                        <?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                </div>
                <script>
document.querySelectorAll('.markdown-body').forEach(div => {
    let raw = div.getAttribute('data-raw');
    
    // 1. 处理 YouTube
    let processed = raw.replace(/{%\s*youtube\s+([^\s%]+)\s*%}/g, (match, id) => {
        return `<div class="video-container"><iframe src="https://www.youtube.com/embed/${id}" allowfullscreen></iframe></div>`;
    });

    // 2. 处理 Bilibili
    processed = processed.replace(/{%\s*bili\s+([^\s%]+)\s*%}/g, (match, bvid) => {
        return `<div class="video-container"><iframe src="//player.bilibili.com/player.html?bvid=${bvid}&page=1&high_quality=1&danmaku=0" scrolling="no" border="0" frameborder="no" framespacing="0" allowfullscreen="true"></iframe></div>`;
    });

    // 3. 处理本地视频 (修正此处逻辑)
    // 建议将第 3 步处理本地视频的逻辑微调为：
processed = processed.replace(/{%\s*localvideo\s+([^\s%]+)\s*%}/g, (match, url) => {
    // 移除 markdown 自动转换可能留下的括号
    let cleanUrl = url.replace(/[()]/g, "").trim(); 
    return `<div style="margin: 20px 0;">
                <video controls style="width: 100%; border-radius: 8px; background: #000;">
                    <source src="${cleanUrl}" type="video/mp4">
                </video>
            </div>`;
});

    // --- 关键一步：使用 marked 解析，并确保 innerHTML 正确接收 ---
    div.innerHTML = marked.parse(processed);
});
                </script>
                <?php if(!$viewFile && count($allFiles) > 5): ?>
                    <p style="text-align:center; color:#999;">更多文章请从列表选择阅读</p>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
<footer class="footer">
    <p>&copy; <?= date('Y') ?> Jackie's Notes. All Rights Reserved.</p>
    
    <div style="margin-top: 15px;">
        <?php if ($isAdmin): ?>
            <a href="?edit=new" class="footer-admin-link">📝 新建文章</a>
            <a href="?logout=1" class="footer-admin-link" style="margin-left:10px;">安全退出</a>
        <?php else: ?>
            <a href="?edit=new" class="footer-admin-link">Management</a>
        <?php endif; ?>
    </div>

    <p style="font-size: 11px; opacity: 0.7; margin-top: 15px;">
        Running on macOS Ventura 13.7 | Powered by Mini CMS
    </p>
</footer>
</div> 
</body>
</html>