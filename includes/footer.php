<footer class="footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> Music Informatics Exam System</p>
    </div>
</footer>

<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style = "";
});
</script>

</body>
</html>