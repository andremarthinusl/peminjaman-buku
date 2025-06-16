    </main>
    
    <!-- Footer -->
    <footer class="bg-indigo-800 text-white py-6">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <h2 class="font-bold text-lg">Perpustakaan Digital</h2>
                    <p class="text-sm text-indigo-200">Sistem Peminjaman Buku Online</p>
                </div>
                <div class="text-sm text-indigo-200">
                    &copy; <?= date('Y') ?> Perpustakaan Digital. Hak cipta dilindungi.
                </div>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script>
        // Mobile menu toggle
        document.querySelector('.mobile-menu-button').addEventListener('click', function() {
            document.querySelector('.mobile-menu').classList.toggle('hidden');
        });

        // Close any alerts after 4 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert-auto-close');
                alerts.forEach(function(alert) {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 500);
                });
            }, 4000);
        });
    </script>
</body>
</html>








































