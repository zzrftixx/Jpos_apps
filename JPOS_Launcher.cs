using System;
using System.IO;
using System.Diagnostics;
using System.Net;
using System.Net.Sockets;
using System.Text;
using System.Threading;
using System.Windows.Forms;
using System.Drawing;

namespace JPOSLauncher
{
    static class Program
    {
        private static Process phpProcess;
        private static NotifyIcon notifyIcon;
        private static int serverPort = 8000;
        private static string appDir;
        private static string phpExe;
        private static Mutex singleInstance;
        private static SplashForm splash;
        private static System.Windows.Forms.Timer pengawasJendela;
        private static bool sedangKeluar;
        private static bool serverSudahDimatikan;

        private static EventWaitHandle sinyalBukaJendela;

        private const string MutexName = "Global\\JPOS_Kasir_JaylaTech_SingleInstance";
        private const string SinyalBukaNama = "Global\\JPOS_Kasir_JaylaTech_BukaJendela";
        private const int ReadinessTimeoutSeconds = 90;

        // --- Ambang batas pengawas jendela (lihat AwasiJendela) --------------------------
        //
        // Halaman kasir mengirim "detak" tiap 10 detik selama terbuka, dan "sinyal tutup"
        // saat ditinggalkan. Detak berikutnya menghapus sinyal tutup.

        private const int JedaPengawasMs = 2000;

        // Berpindah halaman memicu sinyal tutup yang sama persis dengan menutup jendela.
        // Tunggu selama ini sebelum percaya: kalau halaman berikutnya keburu memuat, detaknya
        // sudah menghapus sinyal tutup.
        //
        // Yang menentukan angkanya adalah SEBERAPA LAMA SERVER MEMBALAS, bukan lamanya
        // halaman selesai digambar - detak dikirim begitu HTML-nya sampai. Server PHP di sini
        // melayani satu permintaan pada satu waktu, jadi laporan berat di komputer kasir yang
        // lambat bisa memakan waktu belasan detik. 30 detik memberi jarak yang sangat lebar
        // dari kemungkinan itu; salah menebak ke arah ini cuma berarti server mati beberapa
        // detik lebih lambat - tidak terlihat oleh siapa pun - sedangkan salah ke arah
        // sebaliknya berarti server mati saat kasir masih bekerja.
        private const int KonfirmasiTutupDetik = 30;

        // Jaring pengaman kalau browser mati tanpa sempat mengirim sinyal tutup (mis. ditutup
        // paksa lewat Task Manager). Harus jauh di atas 60 detik: browser memperlambat timer
        // di jendela yang lama tidak aktif sampai sekitar satu kali per menit, dan kasir yang
        // membiarkan JPOS terbuka di latar belakang tidak boleh ikut termatikan.
        private const int DetakHilangDetik = 150;

        [STAThread]
        static void Main()
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);

            appDir = AppDomain.CurrentDomain.BaseDirectory.TrimEnd('\\');

            // 1. Satu instance saja.
            //
            //    Tanpa ini, klik dua kali ikon saat aplikasi sudah jalan akan menyalakan
            //    instance kedua yang justru membunuh server milik instance pertama, lalu
            //    meninggalkan ikon tray yatim dan dua proses menulis ke database SQLite
            //    yang sama.
            bool isFirstInstance;
            singleInstance = new Mutex(true, MutexName, out isFirstInstance);

            if (!isFirstInstance && !AmbilAlihAtauSerahkan())
            {
                return;
            }

            // Dibuat sebelum proses start yang panjang, supaya pintasan yang diklik
            // berulang kali selagi splash tampil tetap tercatat.
            try
            {
                sinyalBukaJendela = new EventWaitHandle(false, EventResetMode.AutoReset, SinyalBukaNama);
            }
            catch (Exception ex)
            {
                Log("Sinyal buka jendela tidak tersedia: " + ex.Message);
            }

            try
            {
                Jalankan();
            }
            catch (Exception ex)
            {
                TutupSplash();
                Log("FATAL: " + ex);
                MessageBox.Show(
                    "JPOS gagal dijalankan.\n\n" + ex.Message + "\n\nDetail lengkap tersimpan di:\n" + PathLog(),
                    "JPOS Gagal Dijalankan", MessageBoxButtons.OK, MessageBoxIcon.Error);
                Application.Exit();
                return;
            }

            Application.ApplicationExit += OnApplicationExit;
            AppDomain.CurrentDomain.ProcessExit += OnApplicationExit;
            Microsoft.Win32.SystemEvents.SessionEnding += (s, e) => StopPhpServer();

            Application.Run();
        }

        /// <summary>
        /// Dipanggil saat pintasan diklik padahal JPOS sudah berjalan.
        ///
        /// Bagi kasir, mengklik pintasan berarti "buka aplikasi kasir" - bukan permintaan
        /// untuk dimarahi. Instance yang sedang berjalan karena itu diminta memunculkan
        /// jendelanya, lalu instance ini pamit tanpa suara.
        ///
        /// Ada satu waktu yang perlu diperhatikan: instance lama bisa saja justru sedang
        /// menutup dirinya sendiri tepat saat permintaan tadi dikirim, sehingga permintaan
        /// itu tidak akan pernah dikerjakan siapa pun. Karena itu hasilnya tidak dipercaya
        /// begitu saja - kalau kunci instance-nya lepas sebentar kemudian, berarti memang
        /// begitu yang terjadi, dan giliran instance inilah yang menyalakan aplikasi.
        /// </summary>
        /// <returns>true kalau instance ini yang harus melanjutkan menyalakan aplikasi.</returns>
        private static bool AmbilAlihAtauSerahkan()
        {
            bool terkirim = MintaJendelaDibuka();

            bool giliranKita;
            try
            {
                giliranKita = singleInstance.WaitOne(terkirim ? 3000 : 20000);
            }
            catch (AbandonedMutexException)
            {
                // Instance lama berhenti tanpa melepas kuncinya. Kuncinya tetap jadi milik
                // kita, dan aplikasinya sudah pasti tidak berjalan lagi.
                giliranKita = true;
            }
            catch
            {
                giliranKita = false;
            }

            if (giliranKita)
            {
                Log("Instance sebelumnya sudah berhenti, instance ini yang melanjutkan.");
                return true;
            }

            if (!terkirim)
            {
                MessageBox.Show(
                    "JPOS sudah berjalan.\n\nCari ikon JPOS di pojok kanan bawah layar (system tray), lalu klik dua kali untuk membuka aplikasi kasir.",
                    "JPOS Sudah Berjalan", MessageBoxButtons.OK, MessageBoxIcon.Information);
            }

            return false;
        }

        private static void Jalankan()
        {
            Log("=== JPOS start ===");

            splash = new SplashForm();
            splash.Show();
            splash.SetStatus("Memeriksa komponen...");
            Application.DoEvents();

            PastikanPhpTersedia();
            MatikanSisaProsesPhp();
            SiapkanFolderDanEnv();
            BersihkanPenandaSesi();

            splash.SetStatus("Mencari port yang tersedia...");
            Application.DoEvents();
            serverPort = CariPortKosong(8000, 8099);
            PerbaruiAppUrl(serverPort);

            // Migrasi + pembangunan cache dikerjakan di sisi PHP (jpos:prepare) supaya
            // logika database berada di tempat yang benar dan ikut teruji otomatis.
            splash.SetStatus("Menyiapkan database & cache...");
            Application.DoEvents();
            JalankanPersiapan();

            splash.SetStatus("Menyalakan server...");
            Application.DoEvents();
            StartPhpServer(serverPort);

            splash.SetStatus("Menunggu server siap...");
            Application.DoEvents();
            TungguServerSiap();

            SetupSystemTray();
            TutupSplash();
            OpenBrowser();
            MulaiPengawasJendela();

            Log("Siap. Port " + serverPort);
        }

        // ------------------------------------------------------------------ logging

        private static string PathLog()
        {
            return Path.Combine(appDir, "storage", "logs", "launcher.log");
        }

        /// <summary>
        /// Versi lama menelan semua kegagalan dengan `catch { }` kosong tanpa jejak sama
        /// sekali, sehingga masalah start-up mustahil didiagnosis dari jarak jauh.
        /// </summary>
        private static void Log(string pesan)
        {
            try
            {
                string file = PathLog();
                Directory.CreateDirectory(Path.GetDirectoryName(file));
                File.AppendAllText(file,
                    "[" + DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss") + "] " + pesan + Environment.NewLine,
                    Encoding.UTF8);
            }
            catch { }
        }

        // ------------------------------------------------------------------ persiapan

        private static void PastikanPhpTersedia()
        {
            phpExe = Path.Combine(appDir, "php", "php.exe");

            // Sengaja TIDAK jatuh ke PHP sistem. Kalau memakai PHP di luar folder aplikasi,
            // pembersihan proses (yang mencocokkan lokasi file) tidak lagi mengenali
            // servernya sendiri, dan proses php.exe menumpuk tanpa pernah dimatikan.
            if (!File.Exists(phpExe))
            {
                throw new Exception(
                    "Komponen PHP tidak ditemukan di:\n" + phpExe +
                    "\n\nKemungkinan besar folder aplikasi belum diekstrak sepenuhnya. Ekstrak ulang seluruh isi ZIP JPOS ke satu folder, lalu jalankan JPOS.exe dari folder tersebut.");
            }

            PastikanPhpBisaDijalankan();
        }

        /// <summary>
        /// Memastikan PHP benar-benar BISA dijalankan, bukan sekadar ada berkasnya.
        ///
        /// php.exe meng-import VCRUNTIME140.DLL. Paket sudah membawanya sendiri, tapi kalau
        /// berkas itu hilang - mis. antivirus mengarantinanya, atau folder disalin sebagian -
        /// Windows menolak menjalankan php.exe dengan kode galat yang tidak menyebut JPOS sama
        /// sekali. Tanpa pemeriksaan ini, kegagalannya muncul jauh di dalam proses persiapan
        /// database dan terbaca seperti kerusakan data.
        /// </summary>
        private static void PastikanPhpBisaDijalankan()
        {
            try
            {
                ProcessStartInfo psi = new ProcessStartInfo();
                psi.FileName = phpExe;
                psi.Arguments = "-v";
                psi.WorkingDirectory = appDir;
                psi.UseShellExecute = false;
                psi.CreateNoWindow = true;
                psi.RedirectStandardOutput = true;
                psi.RedirectStandardError = true;

                using (Process p = Process.Start(psi))
                {
                    string keluaran = p.StandardOutput.ReadToEnd();
                    p.WaitForExit(15000);

                    if (keluaran.IndexOf("PHP", StringComparison.OrdinalIgnoreCase) >= 0) return;
                }
            }
            catch (Exception ex)
            {
                Log("Uji jalan PHP gagal: " + ex.Message);
            }

            string runtimeHilang = "";
            foreach (string dll in new string[] { "vcruntime140.dll", "msvcp140.dll" })
            {
                if (!File.Exists(Path.Combine(appDir, "php", dll))) runtimeHilang += "\n  - php\\" + dll;
            }

            throw new Exception(
                "Komponen PHP ada, tapi tidak bisa dijalankan di komputer ini." +
                (runtimeHilang.Length > 0
                    ? "\n\nBerkas pendukung berikut hilang dari folder aplikasi:" + runtimeHilang +
                      "\n\nKemungkinan besar terhapus antivirus atau folder tidak tersalin sepenuhnya. Ekstrak ulang seluruh isi ZIP JPOS ke satu folder."
                    : "\n\nCoba ekstrak ulang seluruh isi ZIP JPOS ke satu folder, lalu jalankan JPOS.exe dari sana."));
        }

        private static void SiapkanFolderDanEnv()
        {
            string[] folder = new string[]
            {
                Path.Combine(appDir, "storage"),
                Path.Combine(appDir, "storage", "app"),
                Path.Combine(appDir, "storage", "app", "public"),
                Path.Combine(appDir, "storage", "app", "public", "products"),
                Path.Combine(appDir, "storage", "app", "public", "logo"),
                Path.Combine(appDir, "storage", "app", "private"),
                Path.Combine(appDir, "storage", "app", "private", "backups"),
                Path.Combine(appDir, "storage", "framework"),
                Path.Combine(appDir, "storage", "framework", "cache"),
                Path.Combine(appDir, "storage", "framework", "cache", "data"),
                Path.Combine(appDir, "storage", "framework", "sessions"),
                Path.Combine(appDir, "storage", "framework", "views"),
                Path.Combine(appDir, "storage", "logs"),
                Path.Combine(appDir, "bootstrap", "cache"),
                Path.Combine(appDir, "database"),
            };

            foreach (string dir in folder)
            {
                if (!Directory.Exists(dir)) Directory.CreateDirectory(dir);
            }

            string envPath = Path.Combine(appDir, ".env");
            string envExample = Path.Combine(appDir, ".env.example");

            if (!File.Exists(envPath) && File.Exists(envExample))
            {
                File.Copy(envExample, envPath);
                Log(".env dibuat dari .env.example");
            }

            // APP_KEY mengenkripsi cookie sesi dan menandatangani URL, jadi harus unik di tiap
            // toko. Paket dikirim dengan APP_KEY kosong; kunci acak dibuat di sini pada first run
            // dan tidak pernah diganti lagi setelahnya (kalau diganti, semua sesi login gugur).
            if (File.Exists(envPath) && !PunyaAppKey(envPath))
            {
                Log("APP_KEY belum ada, membuat kunci acak baru");
                int kode;
                JalankanArtisan("key:generate --force", 60, out kode);

                if (kode != 0 || !PunyaAppKey(envPath))
                {
                    // Tanpa APP_KEY, Laravel menolak setiap request terenkripsi - aplikasi tidak
                    // akan bisa dipakai. Lebih baik berhenti dengan pesan jelas daripada menyala
                    // setengah jalan.
                    throw new Exception(
                        "Gagal membuat kunci keamanan aplikasi (APP_KEY).\n\nPeriksa " + PathLog() + " untuk keterangan teknisnya.");
                }
            }
        }

        /// <summary>
        /// Apakah .env sudah memuat APP_KEY yang terisi (base64 atau format apa pun yang bukan kosong).
        /// </summary>
        private static bool PunyaAppKey(string envPath)
        {
            foreach (string baris in File.ReadAllLines(envPath))
            {
                string b = baris.Trim();
                if (b.StartsWith("APP_KEY="))
                {
                    return b.Substring("APP_KEY=".Length).Trim().Length > 0;
                }
            }

            return false;
        }

        private static void PerbaruiAppUrl(int port)
        {
            try
            {
                string envPath = Path.Combine(appDir, ".env");
                if (!File.Exists(envPath)) return;

                string target = "APP_URL=http://localhost:" + port;
                string[] baris = File.ReadAllLines(envPath);
                bool berubah = false;

                for (int i = 0; i < baris.Length; i++)
                {
                    if (baris[i].StartsWith("APP_URL="))
                    {
                        if (baris[i].Trim() == target) return; // sudah benar, jangan sentuh file
                        baris[i] = target;
                        berubah = true;
                    }
                }

                if (berubah)
                {
                    File.WriteAllLines(envPath, baris);
                    Log("APP_URL diperbarui ke port " + port);
                }
            }
            catch (Exception ex)
            {
                Log("PerbaruiAppUrl gagal: " + ex.Message);
            }
        }

        private static void JalankanPersiapan()
        {
            int exitCode;
            string keluaran = JalankanArtisan("jpos:prepare", 300, out exitCode);

            if (exitCode != 0)
            {
                throw new Exception(
                    "Persiapan database gagal.\n\n" + Ringkas(keluaran) +
                    "\n\nData Anda tidak diubah. Hubungi JaylaTech dengan menyertakan file log.");
            }
        }

        private static string Ringkas(string teks)
        {
            if (string.IsNullOrEmpty(teks)) return "(tidak ada keterangan dari server)";
            teks = teks.Trim();
            return teks.Length > 600 ? teks.Substring(0, 600) + "..." : teks;
        }

        private static string JalankanArtisan(string argumen, int timeoutDetik)
        {
            int exitCode;
            return JalankanArtisan(argumen, timeoutDetik, out exitCode);
        }

        /// <summary>
        /// Menjalankan artisan dan MEREKAM hasilnya. Versi lama membuang stdout/stderr dan
        /// menelan exception, jadi migrasi yang gagal tetap membiarkan aplikasi menyala di
        /// atas skema database yang salah.
        /// </summary>
        private static string JalankanArtisan(string argumen, int timeoutDetik, out int exitCode)
        {
            exitCode = -1;
            StringBuilder keluaran = new StringBuilder();

            try
            {
                ProcessStartInfo psi = new ProcessStartInfo();
                psi.FileName = phpExe;
                psi.Arguments = "artisan " + argumen;
                psi.WorkingDirectory = appDir;
                psi.UseShellExecute = false;
                psi.CreateNoWindow = true;
                psi.RedirectStandardOutput = true;
                psi.RedirectStandardError = true;
                psi.StandardOutputEncoding = Encoding.UTF8;
                psi.StandardErrorEncoding = Encoding.UTF8;

                using (Process p = new Process())
                {
                    p.StartInfo = psi;
                    p.OutputDataReceived += (s, e) => { if (e.Data != null) lock (keluaran) keluaran.AppendLine(e.Data); };
                    p.ErrorDataReceived += (s, e) => { if (e.Data != null) lock (keluaran) keluaran.AppendLine(e.Data); };

                    p.Start();
                    p.BeginOutputReadLine();
                    p.BeginErrorReadLine();

                    if (!p.WaitForExit(timeoutDetik * 1000))
                    {
                        try { p.Kill(); } catch { }
                        Log("artisan " + argumen + " -> TIMEOUT setelah " + timeoutDetik + " detik");
                        return "Proses melebihi batas waktu " + timeoutDetik + " detik.";
                    }

                    exitCode = p.ExitCode;
                }
            }
            catch (Exception ex)
            {
                Log("artisan " + argumen + " -> EXCEPTION: " + ex.Message);
                return ex.Message;
            }

            string hasil = keluaran.ToString();
            Log("artisan " + argumen + " -> exit " + exitCode + Environment.NewLine + hasil.TrimEnd());

            return hasil;
        }

        // ------------------------------------------------------------------ server

        private static int CariPortKosong(int awal, int akhir)
        {
            for (int port = awal; port <= akhir; port++)
            {
                if (PortTersedia(port)) return port;
            }
            return awal;
        }

        private static bool PortTersedia(int port)
        {
            TcpListener listener = null;
            try
            {
                listener = new TcpListener(IPAddress.Loopback, port);
                listener.Start();
                return true;
            }
            catch
            {
                return false;
            }
            finally
            {
                if (listener != null) { try { listener.Stop(); } catch { } }
            }
        }

        private static void StartPhpServer(int port)
        {
            ProcessStartInfo psi = new ProcessStartInfo();
            psi.FileName = phpExe;
            psi.Arguments = string.Format("artisan serve --host=127.0.0.1 --port={0} --no-reload", port);
            psi.WorkingDirectory = appDir;
            psi.UseShellExecute = false;
            psi.CreateNoWindow = true;
            psi.WindowStyle = ProcessWindowStyle.Hidden;
            psi.RedirectStandardError = true;
            psi.StandardErrorEncoding = Encoding.UTF8;

            phpProcess = new Process();
            phpProcess.StartInfo = psi;
            phpProcess.ErrorDataReceived += (s, e) => { if (e.Data != null) Log("server: " + e.Data); };
            phpProcess.Start();
            phpProcess.BeginErrorReadLine();
        }

        /// <summary>
        /// Menunggu server benar-benar melayani sebelum browser dibuka.
        ///
        /// Versi lama hanya Thread.Sleep(1000) lalu langsung membuka browser. Pada start
        /// pertama - yang menjalankan migrasi, seeding, dan kompilasi seluruh view -
        /// browser terbuka ke port yang belum hidup dan pengguna melihat "situs tidak
        /// dapat dijangkau", yang dibaca sebagai aplikasi rusak.
        /// </summary>
        private static void TungguServerSiap()
        {
            string url = string.Format("http://127.0.0.1:{0}/up", serverPort);
            DateTime batas = DateTime.Now.AddSeconds(ReadinessTimeoutSeconds);

            while (DateTime.Now < batas)
            {
                if (phpProcess != null && phpProcess.HasExited)
                {
                    throw new Exception(
                        "Server PHP berhenti sendiri sebelum siap melayani.\n\nPeriksa " + PathLog() + " untuk keterangan teknisnya.");
                }

                try
                {
                    HttpWebRequest req = (HttpWebRequest)WebRequest.Create(url);
                    req.Timeout = 3000;
                    req.Method = "GET";

                    using (HttpWebResponse res = (HttpWebResponse)req.GetResponse())
                    {
                        if (res.StatusCode == HttpStatusCode.OK)
                        {
                            Log("Server siap setelah " + (int)(ReadinessTimeoutSeconds - (batas - DateTime.Now).TotalSeconds) + " detik");
                            return;
                        }
                    }
                }
                catch
                {
                    // Belum siap - wajar pada detik-detik pertama.
                }

                Thread.Sleep(250);
                Application.DoEvents();
            }

            throw new Exception(
                "Server tidak siap dalam " + ReadinessTimeoutSeconds + " detik.\n\nPeriksa " + PathLog() + " untuk keterangan teknisnya.");
        }

        private static void MatikanSisaProsesPhp()
        {
            try
            {
                foreach (Process p in Process.GetProcessesByName("php"))
                {
                    try
                    {
                        if (p.MainModule != null &&
                            p.MainModule.FileName.StartsWith(appDir, StringComparison.OrdinalIgnoreCase))
                        {
                            Log("Mematikan sisa proses php lama (PID " + p.Id + ")");
                            p.Kill();
                            p.WaitForExit(2000);
                        }
                    }
                    catch { }
                }
            }
            catch { }
        }

        // ------------------------------------------------------------------ UI

        private static void TutupSplash()
        {
            if (splash != null)
            {
                try { splash.Close(); splash.Dispose(); } catch { }
                splash = null;
            }
        }

        private static Icon IkonAplikasi()
        {
            try
            {
                string ico = Path.Combine(appDir, "public", "images", "jpos.ico");
                if (File.Exists(ico)) return new Icon(ico);
            }
            catch { }

            return SystemIcons.Application;
        }

        private static string Versi()
        {
            try
            {
                string file = Path.Combine(appDir, "VERSION");
                if (File.Exists(file)) return File.ReadAllText(file).Trim();
            }
            catch { }

            return "";
        }

        private static void SetupSystemTray()
        {
            ContextMenu menu = new ContextMenu();

            string judul = "JPOS Kasir";
            string versi = Versi();
            if (versi.Length > 0) judul += " v" + versi;

            MenuItem itemJudul = new MenuItem(judul + "  (port " + serverPort + ")");
            itemJudul.Enabled = false;

            MenuItem itemBuka = new MenuItem("Buka Aplikasi Kasir", delegate { OpenBrowser(); });
            itemBuka.DefaultItem = true;
            MenuItem itemFolder = new MenuItem("Buka Folder Aplikasi", delegate { Process.Start("explorer.exe", appDir); });
            MenuItem itemLog = new MenuItem("Lihat Log Teknis", delegate { BukaLog(); });
            MenuItem itemKeluar = new MenuItem("Keluar & Matikan Server", delegate { ExitApp(); });

            menu.MenuItems.Add(itemJudul);
            menu.MenuItems.Add("-");
            menu.MenuItems.Add(itemBuka);
            menu.MenuItems.Add(itemFolder);
            menu.MenuItems.Add(itemLog);
            menu.MenuItems.Add("-");
            menu.MenuItems.Add(itemKeluar);

            notifyIcon = new NotifyIcon();
            notifyIcon.Icon = IkonAplikasi();
            notifyIcon.Text = judul + " - berjalan di port " + serverPort;
            notifyIcon.ContextMenu = menu;
            notifyIcon.Visible = true;
            notifyIcon.DoubleClick += delegate { OpenBrowser(); };

            notifyIcon.ShowBalloonTip(3000, "JPOS siap dipakai",
                "Aplikasi kasir berjalan di http://localhost:" + serverPort + "\nKlik dua kali ikon ini untuk membukanya kembali.",
                ToolTipIcon.Info);
        }

        private static void BukaLog()
        {
            try
            {
                if (File.Exists(PathLog())) Process.Start("notepad.exe", PathLog());
                else MessageBox.Show("Belum ada catatan log.", "JPOS", MessageBoxButtons.OK, MessageBoxIcon.Information);
            }
            catch { }
        }

        private static void OpenBrowser()
        {
            try
            {
                string url = string.Format("http://localhost:{0}", serverPort);

                string edge = CariBrowser(@"Microsoft\Edge\Application\msedge.exe");
                string chrome = CariBrowser(@"Google\Chrome\Application\chrome.exe");

                if (edge != null)
                {
                    Process.Start(edge, "--start-maximized --app=" + url);
                }
                else if (chrome != null)
                {
                    Process.Start(chrome, "--start-maximized --app=" + url);
                }
                else
                {
                    ProcessStartInfo psi = new ProcessStartInfo(url);
                    psi.UseShellExecute = true;
                    Process.Start(psi);
                }
            }
            catch (Exception ex)
            {
                Log("OpenBrowser gagal: " + ex.Message);
                MessageBox.Show(
                    "Tidak bisa membuka browser otomatis.\n\nBuka browser Anda lalu ketik alamat ini:\nhttp://localhost:" + serverPort,
                    "JPOS", MessageBoxButtons.OK, MessageBoxIcon.Information);
            }
        }

        private static string CariBrowser(string relatif)
        {
            string[] basis = new string[]
            {
                Environment.GetFolderPath(Environment.SpecialFolder.ProgramFilesX86),
                Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles),
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            };

            foreach (string b in basis)
            {
                if (string.IsNullOrEmpty(b)) continue;
                string kandidat = Path.Combine(b, relatif);
                if (File.Exists(kandidat)) return kandidat;
            }

            return null;
        }

        // ------------------------------------------------- pengawas jendela aplikasi

        private static string PathPenanda(string nama)
        {
            return Path.Combine(appDir, "storage", "framework", nama);
        }

        /// <summary>
        /// Membuang penanda sesi milik pemakaian SEBELUMNYA.
        ///
        /// Tanpa ini, penanda detak lama (yang umurnya bisa berhari-hari) langsung dianggap
        /// "jendela sudah lama tidak terdengar" dan aplikasi mematikan dirinya sendiri
        /// beberapa detik setelah dinyalakan.
        /// </summary>
        private static void BersihkanPenandaSesi()
        {
            foreach (string nama in new string[] { "jpos-detak", "jpos-tutup" })
            {
                try
                {
                    string path = PathPenanda(nama);
                    if (File.Exists(path)) File.Delete(path);
                }
                catch { }
            }
        }

        private static void MulaiPengawasJendela()
        {
            // Pintasan yang diklik selagi splash masih tampil sudah terjawab oleh jendela
            // yang barusan dibuka; permintaannya dibuang supaya tidak membuka jendela kedua.
            if (sinyalBukaJendela != null)
            {
                try { sinyalBukaJendela.WaitOne(0); } catch { }
            }

            // Timer WinForms, bukan System.Threading.Timer: tick-nya berjalan di thread UI
            // yang sama dengan yang memiliki tray icon dan mutex, jadi penutupan aplikasi
            // dari sini tidak perlu marshalling dan tidak bisa berlomba dengan menu tray.
            pengawasJendela = new System.Windows.Forms.Timer();
            pengawasJendela.Interval = JedaPengawasMs;
            pengawasJendela.Tick += delegate { AwasiJendela(); };
            pengawasJendela.Start();
        }

        /// <summary>
        /// Mematikan server begitu jendela aplikasi ditutup.
        ///
        /// Jendela kasir berjalan di proses browser terpisah, jadi launcher tidak pernah
        /// diberi tahu saat jendelanya ditutup. Dulu akibatnya server PHP terus hidup di
        /// system tray, dan mengklik pintasan lagi cuma memunculkan peringatan
        /// "JPOS sudah berjalan" - kasir harus mencari ikon tray untuk bisa lanjut bekerja.
        ///
        /// Halaman karena itu meninggalkan dua penanda waktu di storage/framework
        /// (lihat public/vendor/jpos-sesi.js): "jpos-detak" selama jendela hidup, dan
        /// "jpos-tutup" saat ditinggalkan.
        ///
        /// KESELAMATAN DATA. Server baru dimatikan setelah 12 detik tanpa satu pun tanda
        /// kehidupan dari browser - saat itu sudah pasti tidak ada permintaan yang sedang
        /// diproses. Kalaupun ada, SQLite mode WAL membuat transaksi yang belum sempat
        /// selesai tidak ikut terbaca lagi tanpa merusak apa pun, dan isi keranjang kasir
        /// yang belum dibayar tersimpan di browser, bukan di server.
        /// </summary>
        private static void AwasiJendela()
        {
            if (sedangKeluar) return;

            try
            {
                // Pintasan diklik lagi selagi aplikasi masih hidup.
                if (sinyalBukaJendela != null && sinyalBukaJendela.WaitOne(0))
                {
                    Log("Pintasan diklik lagi, jendela aplikasi dibuka kembali.");

                    // Penanda lama dibuang lebih dulu: kalau permintaan ini datang tepat di
                    // sela-sela hitungan penutupan, hitungan itu harus batal dan dimulai
                    // ulang dari jendela yang baru.
                    BersihkanPenandaSesi();
                    OpenBrowser();
                    return;
                }

                DateTime detak = WaktuPenanda("jpos-detak");
                DateTime tutup = WaktuPenanda("jpos-tutup");

                // Belum ada detak sama sekali: browser masih dalam perjalanan membuka
                // halaman pertama. Jangan matikan apa pun - kasir juga masih punya ikon
                // tray untuk membukanya sendiri.
                if (detak == DateTime.MinValue) return;

                DateTime sekarang = DateTime.UtcNow;

                // Sinyal tutup yang tidak dibatalkan detak apa pun selama sekian detik:
                // jendelanya benar-benar ditutup, bukan sekadar pindah halaman.
                if (tutup != DateTime.MinValue && (sekarang - tutup).TotalSeconds >= KonfirmasiTutupDetik)
                {
                    Log("Jendela aplikasi ditutup. Server dimatikan.");
                    ExitApp();
                    return;
                }

                if ((sekarang - detak).TotalSeconds >= DetakHilangDetik)
                {
                    Log("Tidak ada tanda jendela aplikasi terbuka selama " + DetakHilangDetik + " detik. Server dimatikan.");
                    ExitApp();
                }
            }
            catch (Exception ex)
            {
                // Pengawas ini kenyamanan, bukan keharusan. Kalau penandanya tidak terbaca,
                // aplikasi tetap berjalan dan masih bisa ditutup lewat menu tray.
                Log("Pengawas jendela: " + ex.Message);
            }
        }

        /// <summary>
        /// Meminta instance yang sedang berjalan memunculkan jendela aplikasinya.
        /// Mengembalikan false kalau permintaannya tidak sampai - misalnya karena instance
        /// lama sedang dalam proses menutup diri.
        /// </summary>
        private static bool MintaJendelaDibuka()
        {
            try
            {
                EventWaitHandle sinyal = EventWaitHandle.OpenExisting(SinyalBukaNama);
                sinyal.Set();
                return true;
            }
            catch
            {
                return false;
            }
        }

        private static DateTime WaktuPenanda(string nama)
        {
            string path = PathPenanda(nama);

            if (!File.Exists(path)) return DateTime.MinValue;

            return File.GetLastWriteTimeUtc(path);
        }

        // ------------------------------------------------------------------ keluar

        private static void ExitApp()
        {
            // Pengawas jendela berdetak tiap 2 detik; tanpa penjaga ini penutupan bisa
            // dipicu dua kali dan menjalankan seluruh urutan keluar berbarengan.
            if (sedangKeluar) return;
            sedangKeluar = true;

            // Dilepas paling awal supaya pintasan yang diklik tepat saat aplikasi sedang
            // menutup diri tidak mengirim permintaan ke instance yang sudah tidak akan
            // mengerjakannya. Instance baru akan menunggu giliran, lalu menyalakan ulang.
            if (sinyalBukaJendela != null)
            {
                try { sinyalBukaJendela.Close(); } catch { }
                sinyalBukaJendela = null;
            }

            if (pengawasJendela != null)
            {
                try { pengawasJendela.Stop(); pengawasJendela.Dispose(); } catch { }
                pengawasJendela = null;
            }

            if (notifyIcon != null)
            {
                notifyIcon.Visible = false;
                notifyIcon.Dispose();
                notifyIcon = null;
            }

            StopPhpServer();
            BersihkanPenandaSesi();
            Application.Exit();
        }

        private static void OnApplicationExit(object sender, EventArgs e)
        {
            StopPhpServer();

            if (singleInstance != null)
            {
                try { singleInstance.ReleaseMutex(); } catch { }
                singleInstance = null;
            }
        }

        private static void StopPhpServer()
        {
            // Dipanggil dari tiga jalur yang bisa saling menyusul: menu tray, pengawas
            // jendela, dan Windows yang sedang dimatikan. Cukup sekali saja - terutama
            // karena langkah merapikan database di bawah menjalankan proses PHP baru.
            if (serverSudahDimatikan) return;
            serverSudahDimatikan = true;

            if (phpProcess != null)
            {
                try
                {
                    if (!phpProcess.HasExited)
                    {
                        phpProcess.Kill();
                        phpProcess.WaitForExit(3000);
                    }
                    phpProcess.Dispose();
                }
                catch { }
                phpProcess = null;
            }

            // `artisan serve` menyalakan proses `php -S` terpisah sebagai anak. Mematikan
            // induknya saja meninggalkan anak itu tetap hidup dan menahan portnya.
            MatikanSisaProsesPhp();
            Log("Server dimatikan.");

            RapikanDatabase();
        }

        /// <summary>
        /// Memindahkan sisa WAL ke berkas database utama setelah server benar-benar mati.
        ///
        /// SQLite mode WAL menaruh perubahan terbaru di berkas pendamping `-wal` dulu. Itu
        /// yang membuat data tetap utuh saat aplikasi tertutup mendadak, tapi juga berarti
        /// berkas `jpos.sqlite` sendirian belum berisi transaksi paling akhir. Client yang
        /// menyalin berkas itu ke flashdisk atau komputer lain akan mendapat data yang
        /// tertinggal kalau tidak dirapikan lebih dulu.
        ///
        /// Dijalankan SETELAH server mati supaya tidak berebut kunci database, dan dibatasi
        /// waktunya supaya aplikasi tidak pernah menggantung saat ditutup.
        /// </summary>
        private static void RapikanDatabase()
        {
            if (string.IsNullOrEmpty(phpExe) || !File.Exists(phpExe)) return;

            try
            {
                JalankanArtisan("jpos:rapikan-database", 20);
            }
            catch (Exception ex)
            {
                Log("Merapikan database dilewati: " + ex.Message);
            }
        }
    }

    /// <summary>
    /// Jendela kecil selama proses start, supaya pengguna tahu aplikasi sedang bekerja
    /// dan bukan menggantung - start pertama bisa memakan puluhan detik karena
    /// menjalankan migrasi, seeding, dan kompilasi seluruh tampilan.
    /// </summary>
    internal class SplashForm : Form
    {
        private Label labelStatus;

        public SplashForm()
        {
            FormBorderStyle = FormBorderStyle.None;
            StartPosition = FormStartPosition.CenterScreen;
            Size = new Size(360, 140);
            BackColor = Color.FromArgb(15, 23, 42);
            ShowInTaskbar = false;
            TopMost = true;

            Label judul = new Label();
            judul.Text = "JPOS Kasir";
            judul.ForeColor = Color.White;
            judul.Font = new Font("Segoe UI", 16F, FontStyle.Bold);
            judul.AutoSize = false;
            judul.TextAlign = ContentAlignment.MiddleCenter;
            judul.Dock = DockStyle.Top;
            judul.Height = 60;

            labelStatus = new Label();
            labelStatus.Text = "Memulai...";
            labelStatus.ForeColor = Color.FromArgb(148, 163, 184);
            labelStatus.Font = new Font("Segoe UI", 9F);
            labelStatus.AutoSize = false;
            labelStatus.TextAlign = ContentAlignment.MiddleCenter;
            labelStatus.Dock = DockStyle.Fill;

            Label footer = new Label();
            footer.Text = "by JaylaTech";
            footer.ForeColor = Color.FromArgb(71, 85, 105);
            footer.Font = new Font("Segoe UI", 8F);
            footer.AutoSize = false;
            footer.TextAlign = ContentAlignment.MiddleCenter;
            footer.Dock = DockStyle.Bottom;
            footer.Height = 24;

            Controls.Add(labelStatus);
            Controls.Add(judul);
            Controls.Add(footer);
        }

        public void SetStatus(string teks)
        {
            labelStatus.Text = teks;
            labelStatus.Refresh();
        }
    }
}
