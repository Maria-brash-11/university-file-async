<!DOCTYPE html>
<html>
<body>
  <h1>Генератор нагрузки</h1>
  <button onclick="sendFiles()">🚀 Отправить 10 задач</button>
  <div id="log"></div>
  <script>
  async function sendFiles() {
    const log = document.getElementById('log');
    for(let i=1; i<=10; i++) {
      const blob = new Blob(['test content ' + i], {type: 'text/plain'});
      const file = new File([blob], `test_${i}.txt`, {type: 'text/plain'});
      const fd = new FormData();
      fd.append('file', file);
      
      const res = await fetch('/local/ajax/upload.php', {method:'POST', body:fd});
      log.innerHTML += `Задача #${i} отправлена<br>`;
    }
  }
  </script>
</body>
</html>