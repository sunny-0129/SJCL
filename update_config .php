<?php
// 设置返回 JSON 格式
header('Content-Type: application/json');

// 引入数据库连接文件
require_once '../../console/shujuku.php';

// 检查是否为 POST 请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取原始 POST 数据
    $data = json_decode(file_get_contents('php://input'), true);

    $id = isset($data['id']) ? intval($data['id']) : null;
    $domain_name = isset($data['domain_name']) ? trim($data['domain_name']) : null;
    $domain_type_name = isset($data['domain_type']) ? trim($data['domain_type']) : null; // 接收中文类型
    $transport_protocol = isset($data['transport_protocol']) ? trim($data['transport_protocol']) : null;
    $user_groups = isset($data['user_groups']) ? trim($data['user_groups']) : null; // 接收授权用户组 ID 列表
    $nickname = isset($data['nickname']) ? trim($data['nickname']) : null; // 接收昵称

    // 检查必要的字段是否为空
    if (!$id || !$domain_name || !$domain_type_name || !$transport_protocol || !$user_groups || !$nickname) {
        echo json_encode([
            'status' => 'error',
            'message' => '所有字段都是必填项'
        ]);
        exit;
    }

    // 查询当前记录
    $sql = "SELECT domain_name, type, transport_protocol, authorize FROM config WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result($original_domain_name, $original_type, $original_transport_protocol, $original_authorize);
    
    if (!$stmt->fetch()) {
        echo json_encode([
            'status' => 'error',
            'message' => '未找到记录'
        ]);
        exit;
    }
    $stmt->close();

    // 查询域名类型的 ID
    $sql = "SELECT id FROM domainname_type WHERE name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $domain_type_name);
    $stmt->execute();
    $stmt->bind_result($domain_type_id);
    
    if (!$stmt->fetch()) {
        echo json_encode([
            'status' => 'error',
            'message' => '无效的域名类型'
        ]);
        exit;
    }
    $stmt->close();

    // 检查数据库中是否已存在相同的域名和类型
    $sql = "SELECT COUNT(*) FROM config WHERE domain_name = ? AND type = ? AND id != ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $domain_name, $domain_type_id, $id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0) {
        echo json_encode([
            'status' => 'error',
            'message' => "该域名在{$domain_type_name}中已存在"
        ]);
        exit;
    }

    // 获取用户组名称
    $user_groups_array = explode(',', $user_groups);
    $user_group_names = [];
    foreach ($user_groups_array as $group_id) {
        $sql = "SELECT name FROM user_groups WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $group_id);
        $stmt->execute();
        $stmt->bind_result($group_name);
        if ($stmt->fetch()) {
            $user_group_names[] = $group_name;
        }
        $stmt->close();
    }
    $user_groups_string = implode(',', $user_group_names);
    
    // 查询 nickname 对应的 user id
    $userStmt = $conn->prepare("SELECT id FROM users WHERE nickname = ?");
    $userStmt->bind_param("s", $nickname);
    $userStmt->execute();
    $userResult = $userStmt->get_result();

    if ($userResult->num_rows === 0) {
    die(json_encode(['status' => 'error', 'message' => '未找到对应的用户']));
    }

    $userRow = $userResult->fetch_assoc();
    $userId = $userRow['id'];
    
    // 查询用户在 user_positions 中对应的 positions_id
    $positionStmt = $conn->prepare("SELECT position_id FROM user_positions WHERE user_id = ?");
    $positionStmt->bind_param('i', $userId);
    $positionStmt->execute();
    $positionStmt->bind_result($position_id);
    if (!$positionStmt->fetch()) {
        die(json_encode(['status' => 'error', 'message' => '用户未分配职位']));
    }
    $positionStmt->close();

    // 查询 positions_id 对应的 permission_id
    $permissionStmt = $conn->prepare("SELECT permission_id FROM position_permissions WHERE position_id = ?");
    $permissionStmt->bind_param('i', $position_id);
    $permissionStmt->execute();
    $permissionResult = $permissionStmt->get_result();
    $has_permission = false;

    while ($permissionRow = $permissionResult->fetch_assoc()) {
        if ($permissionRow['permission_id'] == 34) {
            $has_permission = true;
            break;
        }
    }

    $permissionStmt->close();

    // 如果没有权限，提示“没有权限执行此操作”
    if (!$has_permission) {
        die(json_encode(['status' => 'error', 'message' => '没有权限执行此操作']));
    }

    // 查询 old-url 和 new-url
    $old_url = $original_transport_protocol . $original_domain_name; // 加上协议部分
    $new_url = $transport_protocol . $domain_name; // 新的 URL

    // 根据 domain_type_id 和 user_groups 来匹配并更新 short_links 表
    $user_groups_array = explode(',', $user_groups); // 分解 user_groups 字符串

    // 循环遍历每个用户组，执行不同的更新操作
    foreach ($user_groups_array as $group_id) {
        $sql = "";
        $stmt = null; // 初始化语句句柄

        // 根据不同的 domain_type_id 执行不同的匹配逻辑
        if ($domain_type_id == 1) {
            // 匹配 rk_domain 和 user_groups，确保 old_url 和 rk_domain 一致
            $sql = "UPDATE short_links SET rk_domain = ? WHERE rk_domain = ? AND user_groups = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sss', $new_url, $old_url, $group_id);
        } elseif ($domain_type_id == 2) {
            // 匹配 ld_domain 和 user_groups，确保 old_url 和 ld_domain 一致
            $sql = "UPDATE short_links SET ld_domain = ? WHERE ld_domain = ? AND user_groups = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sss', $new_url, $old_url, $group_id);
        } elseif ($domain_type_id == 3) {
            // 匹配 dl_domain 和 short_url，确保 old_url 和 dl_domain 一致，同时更新 short_url
            $sql = "UPDATE short_links SET dl_domain = ?, short_url = ? WHERE dl_domain = ? AND short_url = ? AND user_groups = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssss', $new_url, $new_url, $old_url, $old_url, $group_id);
        }

        // 执行更新操作
        if ($stmt) {
            $stmt->execute();
            $stmt->close(); // 关闭语句句柄
        }
    }

    // 检查是否有修改
    $has_changes = false;
    $changes = [];

    if ($original_domain_name !== $domain_name) {
        $changes[] = "域名从 '{$original_domain_name}' 修改为 '{$domain_name}'";
        $has_changes = true;
    }

    // 比较域名类型 ID
    if ($original_type != $domain_type_id) {
        // 获取原域名类型的中文名
        $sql = "SELECT name FROM domainname_type WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $original_type);
        $stmt->execute();
        $stmt->bind_result($original_type_name);
        $stmt->fetch();
        $stmt->close();

        $changes[] = "域名类型从 '{$original_type_name}' 修改为 '{$domain_type_name}'";
        $has_changes = true;
    }

    if ($original_transport_protocol !== $transport_protocol) {
        $changes[] = "传输协议从 '{$original_transport_protocol}' 修改为 '{$transport_protocol}'";
        $has_changes = true;
    }

    if ($original_authorize !== $user_groups) {
        // 将原授权用户组转换为中文
        $original_user_group_names = [];
        $original_group_ids = explode(',', $original_authorize);
        foreach ($original_group_ids as $group_id) {
            $sql = "SELECT name FROM user_groups WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $group_id);
            $stmt->execute();
            $stmt->bind_result($group_name);
            if ($stmt->fetch()) {
                $original_user_group_names[] = $group_name;
            }
            $stmt->close();
        }
        $original_user_groups_string = implode(',', $original_user_group_names);

        $changes[] = "授权用户组从 '{$original_user_groups_string}' 修改为 '{$user_groups_string}'";
        $has_changes = true;
    }
    
// 如果没有任何更改，直接返回“没有任何内容被更新”
if (!$has_changes) {
    echo json_encode([
        'status' => 'info',
        'message' => '没有任何内容被更新'
    ]);
    exit;
}
    // 更新数据库的 SQL 语句
    $sql = "UPDATE config 
            SET domain_name = ?, type = ?, transport_protocol = ?, authorize = ? 
            WHERE id = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('ssssi', $domain_name, $domain_type_id, $transport_protocol, $user_groups, $id);
        if ($stmt->execute()) {
            // 更新成功
            if ($has_changes) {
                $timestamp = date('Y-m-d H:i:s');
                $action = "在配置中心将" . implode('，', $changes) . "。";
                
                // 记录到 action_logs 表
                $sql = "INSERT INTO action_logs (action, nickname, timestamp) VALUES (?, ?, ?)";
                $stmt_log = $conn->prepare($sql);
                $stmt_log->bind_param('sss', $action, $userId, $timestamp);
                $stmt_log->execute();
                $stmt_log->close();
            }

            echo json_encode([
                'status' => 'success',
                'message' => '配置已成功更新'
            ]);
        } else {
            // 更新失败
            echo json_encode([
                'status' => 'error',
                'message' => '更新失败: ' . $stmt->error
            ]);
        }
        $stmt->close();
    } else {
        // SQL 准备失败
        echo json_encode([
            'status' => 'error',
            'message' => 'SQL 语句准备失败: ' . $conn->error
        ]);
    }

    // 关闭数据库连接
    $conn->close();
} else {
    // 非 POST 请求处理
    echo json_encode([
        'status' => 'error',
        'message' => '无效的请求方法'
    ]);
}
?>   