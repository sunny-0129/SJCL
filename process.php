<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 获取用户输入的逗号分隔数据和提取位数
    $inputData = $_POST['inputData'];
    $extractLength = isset($_POST['extractLength']) ? intval($_POST['extractLength']) : 0;
    $commaSeparated = isset($_POST['commaSeparated']); // 勾选是否执行逗号分隔转行
    $extractData = isset($_POST['extractData']); // 勾选是否提取位数数据

    // 检查输入是否为空
    if (empty($inputData)) {
        $error_message = '转换内容不能为空';
        header('Location: index.php?error=' . urlencode($error_message));
        exit;
    }

    // 确保至少勾选一个选项
    if (!$commaSeparated && !$extractData) {
        $error_message = '请选择至少一个操作：逗号分隔转行或提取位数数据';
        header('Location: index.php?error=' . urlencode($error_message));
        exit;
    }

    // 将输入数据按行拆分
    $dataArray = explode("\n", $inputData);
    $dataArray = array_map('trim', $dataArray);  // 去除每行前后空白

    // 处理逗号分隔转行
    if ($commaSeparated) {
        $newDataArray = [];
        foreach ($dataArray as $item) {
            $subItems = explode(',', $item);
            foreach ($subItems as $subItem) {
                $newDataArray[] = trim($subItem);
            }
        }
        $dataArray = $newDataArray;
    }

    // 处理提取位数数据
    if ($extractData) {
        // 检查提取位数是否有效
        if ($extractLength <= 0) {
            $error_message = '请输入有效的位数';
            header('Location: index.php?error=' . urlencode($error_message));
            exit;
        }

        // 提取每个项目的前 N 位
        $dataArray = array_map(function($item) use ($extractLength) {
            // 提取每行数据的前 N 位
            return substr(trim($item), 0, $extractLength);
        }, $dataArray);
    }

    // 将结果转换为按行显示
    $result = implode("\n", $dataArray);

    // 将结果传递到原页面进行显示
    header('Location: index.php?result=' . urlencode($result));
    exit;
}
?>