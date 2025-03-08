<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <!-- 设置网站图标 -->
    <link rel="shortcut icon" href="zhuanghuan.png" type="image/png">
    <title>数据处理工具</title>
    <style>
        /* 页面背景和整体布局 */
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f7fc;
            color: #333;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            box-sizing: border-box;
        }

        .container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 30px;
            width: 80%;
            max-width: 700px;
        }

        h1 {
            text-align: center;
            color: #4e73df;
            font-size: 2rem;
            margin-bottom: 20px;
        }

        label {
            font-size: 1.1rem;
            margin-bottom: 8px;
            display: block;
        }

        textarea, input[type="number"], input[type="submit"] {
            width: 100%;
            padding: 12px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 6px;
            margin-bottom: 15px;
            font-family: 'Arial', sans-serif;
            transition: border-color 0.3s ease;
        }

        textarea:focus, input[type="number"]:focus {
            border-color: #4e73df;
            outline: none;
        }

        input[type="submit"] {
            background-color: #4e73df;
            color: white;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
            width: 100%;
        }

        input[type="submit"]:hover {
            background-color: #375a9c;
        }

        .result-section {
            margin-top: 30px;
        }

        .result-section textarea {
            height: 250px;
            border-color: #ddd;
        }

        .download-btn {
            display: block;
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-align: center;
            margin-top: 15px;
            width: 100%;
            font-size: 1rem;
            transition: background-color 0.3s ease;
        }

        .download-btn:hover {
            background-color: #218838;
        }

        .error-message {
            color: red;
            font-size: 1rem;
            text-align: center;
            margin-top: 20px;
        }

        /* 使用 Flexbox 布局确保勾选框和文字在同一行并居中 */
        .checkbox-container {
            display: flex;
            align-items: center; /* 垂直居中 */
            margin-right: 20px; /* 为两个勾选框之间添加间距 */
        }

        .checkbox-container label {
            margin-left: 10px; /* 为勾选框和文字之间添加适当间距 */
        }

        .extract-length-container {
            display: none; /* 默认隐藏 */
        }

        /* 新添加的容器样式，用于将两个勾选框放在同一行 */
        .checkboxes-wrapper {
            display: flex;
            flex-wrap: wrap;
            margin-top: 15px;
            margin-bottom: 15px; /* 适当的上下间距 */
        }
        
        
        .Convert {
            display: block;
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-align: center;
            margin-top: 15px;
            width: 100%;
            font-size: 1rem;
            transition: background-color 0.3s ease;
        }

        .Convert:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
   <div class="container">
        <h1>数据处理工具</h1>

        <form id="dataForm">
            <textarea name="inputData" id="inputData" rows="10" cols="50" placeholder="请输入需要处理的数据"></textarea>

            <div class="checkboxes-wrapper">
                <div class="checkbox-container">
                    <input type="checkbox" name="commaSeparated" id="commaSeparated">
                    <label for="commaSeparated">勾选此项进行逗号分隔转行</label>
                </div>
                <div class="checkbox-container">
                    <input type="checkbox" name="extractData" id="extractData" onchange="toggleExtractLength()">
                    <label for="extractData">勾选此项提取位数数据</label>
                </div>
            </div>

            <div class="extract-length-container" id="extractLengthContainer">
                <label for="extractLength">请输入要提取的位数：</label>
                <input type="number" id="extractLength" name="extractLength" min="1" placeholder="例如：7">
            </div>

            <button type="button" id="processButton" class="Convert">转换</button>
        </form>

        <div id="resultSection" class="result-section" style="display: none;">
            <h3>转换结果：</h3>
            <textarea id="resultText" readonly></textarea>
            <button id="downloadBtn" class="download-btn" style="display: none;">下载 CSV 文件</button>
        </div>

        <p id="errorMessage" class="error-message" style="display: none;"></p>
    </div>

    <script>
        // 根据勾选框状态来显示或隐藏位数输入框
        function toggleExtractLength() {
            const extractLengthContainer = document.getElementById('extractLengthContainer');
            const extractDataCheckbox = document.getElementById('extractData');
            
            // 如果勾选了提取位数数据，则显示输入框，否则隐藏
            if (extractDataCheckbox.checked) {
                extractLengthContainer.style.display = 'block';
            } else {
                extractLengthContainer.style.display = 'none';
            }
        }

        // 处理数据并存储到浏览器
        document.getElementById('processButton').addEventListener('click', function() {
            const inputData = document.getElementById('inputData').value;
            const commaSeparated = document.getElementById('commaSeparated').checked;
            const extractData = document.getElementById('extractData').checked;
            const extractLength = document.getElementById('extractLength').value;

            // 处理数据：逗号分隔转行
            let resultData = inputData;
            if (commaSeparated) {
                resultData = resultData.split(',').join('\n');
            }

            // 处理位数提取
            if (extractData && extractLength) {
                resultData = resultData.split('\n').map(line => {
                    return line.substring(0, extractLength);
                }).join('\n');
            }

            // 存储结果到 localStorage
            localStorage.setItem('processedData', resultData);

            // 显示结果并提供下载按钮
            document.getElementById('resultSection').style.display = 'block';
            document.getElementById('resultText').value = resultData;
            document.getElementById('downloadBtn').style.display = 'inline-block';
            document.getElementById('errorMessage').style.display = 'none';
        });

        // 下载 CSV 文件
        document.getElementById('downloadBtn').addEventListener('click', function() {
            const data = localStorage.getItem('processedData');
            const blob = new Blob([data], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'processed_data.csv';
            a.click();
            URL.revokeObjectURL(url);
        });
    </script>
</body>
</html>
