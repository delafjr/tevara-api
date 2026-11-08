<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\LecturerProfile;
use App\Models\StudentProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ──────────────────────────────────────────
        // PENGURUS TEFA
        // ──────────────────────────────────────────
        $pengurus = User::firstOrCreate(
            ['email' => 'pengurus@tevara.id'],
            [
                'name'         => 'Admin Pengurus TeFa',
                'password'     => Hash::make('password'),
                'phone_number' => '628112345678',
            ]
        );
        $pengurus->syncRoles(['pengurus_tefa']);

        // ──────────────────────────────────────────
        // JURUSAN
        // ──────────────────────────────────────────
        $jurusan = User::firstOrCreate(
            ['email' => 'jurusan.ti@polines.ac.id'],
            [
                'name'         => 'Jurusan Teknik Informatika',
                'password'     => Hash::make('password'),
                'phone_number' => '62243562700',
            ]
        );
        $jurusan->syncRoles(['jurusan']);

        // ──────────────────────────────────────────
        // DOSEN — password = nidn (tanpa titik/spasi)
        // nidn urutan 1–25 dibuat sequential: 1,2,...,25
        // ──────────────────────────────────────────
        $dosen = [
            ['name' => 'Prof. Dr. Ir. Kurnianingsih, S.T., M.T.',    'email' => 'kurnianingsih@polines.ac.id',    'phone' => '6281326780597', 'nidn' => '0000000001'],
            ['name' => 'Nurseno Bayu Aji, S.Kom., M.Kom.',           'email' => 'bayu.nurseno@polines.ac.id',     'phone' => '6282226179154', 'nidn' => '0000000002'],
            ['name' => 'Wiktasari, S.T., M.Kom.',                    'email' => 'wiktasari@polines.ac.id',        'phone' => '6281226381271', 'nidn' => '0000000003'],
            ['name' => 'Muhammad Irwan Yanwari, S.Kom., M.Eng.',     'email' => 'irwan.yanwari@polines.ac.id',    'phone' => '6288899999900', 'nidn' => '0000000004'],
            ['name' => 'Kuwat Santoso, S.T., M.Kom.',                'email' => 'kuwatsantoso@polines.ac.id',     'phone' => '6285723114203', 'nidn' => '0000000005'],
            ['name' => 'Prayitno, S.ST., M.T.',                      'email' => 'prayitnox@gmail.com',            'phone' => '6281117881717', 'nidn' => '0000000006'],
            ['name' => 'Mardiyono, S.Kom., M.Sc.',                   'email' => 'mardiyono@polines.ac.id',        'phone' => '628112782211',  'nidn' => '0000000007'],
            ['name' => 'Tri Raharjo Yudantoro, S.Kom., M.Kom.',      'email' => 'tryudan@gmail.com',              'phone' => '628122885797',  'nidn' => '0000000008'],
            ['name' => 'Slamet Handoko, S.Kom., M.Kom.',             'email' => 'hands101polines@gmail.com',      'phone' => '6289090909090', 'nidn' => '0000000009'],
            ['name' => 'Wahyu Sulistiyo, S.T., M.Kom.',              'email' => 'wahyu.sulistiyo@polines.ac.id',  'phone' => '6212121212111', 'nidn' => '0000000010'],
            ['name' => 'Amran Yobioktabera, S.Kom., M.Kom.',         'email' => 'amran.yobi@gmail.com',           'phone' => '6285640202763', 'nidn' => '0000000011'],
            ['name' => 'Aisyatul Karima, S.Kom., MCS.',              'email' => 'aisya.karima@polines.ac.id',     'phone' => '6209887654456', 'nidn' => '0000000012'],
            ['name' => 'Muttabik Fathul Lathief, S.Kom., M.Eng.',    'email' => 'muttabik.lathief@gmail.com',     'phone' => '6285643468896', 'nidn' => '0000000013'],
            ['name' => 'Sirli Fahriah, S.Kom., M.Kom.',              'email' => 'sirlifahriah@polines.ac.id',     'phone' => '6282322769994', 'nidn' => '0000000014'],
            ['name' => 'Angga Wahyu Wibowo, S.Kom., M.Eng.',         'email' => 'anggawahyuwibowo@polines.ac.id', 'phone' => '6285640778897', 'nidn' => '0000000015'],
            ['name' => 'Liliek Triyono, S.T., M.Kom.',               'email' => 'liliek.triyono@gmail.com',       'phone' => '6285725150360', 'nidn' => '0000000016'],
            ['name' => 'Drs. Parsumo Rahardjo, M.Kom.',              'email' => 'parsumo@polines.ac.id',          'phone' => '628122933403',  'nidn' => '0000000017'],
            ['name' => 'Afandi Nur Aziz Thohari, S.T., M.Cs.',       'email' => 'afandi@polines.ac.id',           'phone' => '628787990909',  'nidn' => '0000000018'],
            ['name' => 'Idhawati Hestiningsih, S.Kom., M.Kom.',      'email' => 'hestidha@polines.ac.id',         'phone' => '628122848418',  'nidn' => '0000000019'],
            ['name' => 'Sukamto, S.Kom., M.T.',                      'email' => 'sukamto@polines.ac.id',          'phone' => '6234356767809', 'nidn' => '0000000020'],
            ['name' => 'Eri Eli Lavindi, S.Kom., M.Kom.',            'email' => 'erielilavindi@polines.ac.id',    'phone' => '6289667978096', 'nidn' => '0000000021'],
            ['name' => 'Tahan Prahara, S.T., M.Kom.',                'email' => 'tahanprahara@polines.ac.id',     'phone' => '6281234567301', 'nidn' => '0000000022'],
            ['name' => 'Arif Fitra Setyawan, S.Pd., M.Kom.',         'email' => 'ariffitrasetyawan@polines.ac.id','phone' => '6245345345234', 'nidn' => '0000000023'],
            ['name' => 'Filda Hulwani Dewi, M.Pd.',                  'email' => 'fildahulwanidewi@polines.ac.id', 'phone' => '6234567345622', 'nidn' => '0000000024'],
            ['name' => 'Suko Tyas Pernanda, M.Cs.',                  'email' => 'sukotyaspernanda@polines.ac.id', 'phone' => '6234343409090', 'nidn' => '0000000025'],
        ];

        foreach ($dosen as $d) {
            $user = User::firstOrCreate(
                ['email' => $d['email']],
                [
                    'name'         => $d['name'],
                    'password'     => Hash::make($d['nidn']),
                    'phone_number' => $d['phone'],
                ]
            );

            // Kuwat Santoso dapat 2 role
            $roles = ['dosen'];
            if ($d['email'] === 'kuwatsantoso@polines.ac.id' || $d['email'] === 'wiktasari@polines.ac.id') {
                $roles[] = 'pengurus_tefa';
            }
            $user->syncRoles($roles);

            LecturerProfile::firstOrCreate(
                ['user_id' => $user->id],
                ['nidn' => $d['nidn']]
            );
        }

        // ──────────────────────────────────────────
        // MAHASISWA — password = nim tanpa titik
        // ──────────────────────────────────────────
        $mahasiswa = [
            // Kelas 2B (43325201–43325225)
            ['name' => 'Aang Kunadi',                             'email' => 'aang.43325201@mhs.polines.ac.id',      'phone' => '6283163208199',  'nim' => '4.33.25.2.01', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325201'],
            ['name' => 'Alvian Reza Mahardika',                   'email' => 'alvian.43325202@mhs.polines.ac.id',    'phone' => '6283141265345',  'nim' => '4.33.25.2.02', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325202'],
            ['name' => 'Amanda Christina Situmorang',             'email' => 'amanda.43325203@mhs.polines.ac.id',    'phone' => '6283123552567',  'nim' => '4.33.25.2.03', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325203'],
            ['name' => 'Azka Galuh Basuki',                       'email' => 'azka.43325204@mhs.polines.ac.id',      'phone' => '6281225441850',  'nim' => '4.33.25.2.04', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325204'],
            ['name' => 'Bagas Idden Listiyanto',                  'email' => 'bagas.43325205@mhs.polines.ac.id',     'phone' => '6285821132811',  'nim' => '4.33.25.2.05', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325205'],
            ['name' => 'Cheetah Amrullah Swastika',               'email' => 'cheetah.43325206@mhs.polines.ac.id',   'phone' => '6289699982833',  'nim' => '4.33.25.2.06', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325206'],
            ['name' => 'Dela Fajar Mulia',                        'email' => 'dela.43325207@mhs.polines.ac.id',      'phone' => '6288228893279',  'nim' => '4.33.25.2.07', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325207'],
            ['name' => 'Evan Octavian Ramadhan',                  'email' => 'evan.43325208@mhs.polines.ac.id',      'phone' => '628124097657',   'nim' => '4.33.25.2.08', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325208'],
            ['name' => 'Hadziq Vinu Mufidany',                    'email' => 'hadziq.43325209@mhs.polines.ac.id',    'phone' => '628882434106',   'nim' => '4.33.25.2.09', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325209'],
            ['name' => 'Irgi Akbar Fahlevi',                      'email' => 'irgi.43325210@mhs.polines.ac.id',      'phone' => '6285865068627',  'nim' => '4.33.25.2.10', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325210'],
            ['name' => 'Jatmiko Satrio Wibowo',                   'email' => 'miko.43325211@mhs.polines.ac.id',      'phone' => '6287882919424',  'nim' => '4.33.25.2.11', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325211'],
            ['name' => 'Laksamana Agung Hadi Nugroho',            'email' => 'aksa.43325212@mhs.polines.ac.id',      'phone' => '6281229823032',  'nim' => '4.33.25.2.12', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325212'],
            ['name' => 'Miladiyah Arinal Haq',                    'email' => 'arin.43325213@mhs.polines.ac.id',      'phone' => '6285866837036',  'nim' => '4.33.25.2.13', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325213'],
            ['name' => 'Muhammad Baruna Saylendra',               'email' => 'baruna.43325214@mhs.polines.ac.id',    'phone' => '62895616790109', 'nim' => '4.33.25.2.14', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325214'],
            ['name' => 'Muhammad Fadhil',                         'email' => 'fadhil.43325215@mhs.polines.ac.id',    'phone' => '6282228884636',  'nim' => '4.33.25.2.15', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325215'],
            ['name' => 'Naufal Azka Fadhlillah',                  'email' => 'azka.43325216@mhs.polines.ac.id',      'phone' => '62895422628571', 'nim' => '4.33.25.2.16', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325216'],
            ['name' => 'Nazifa Citra Nurviani',                   'email' => 'citra.43325217@mhs.polines.ac.id',     'phone' => '6283844508171',  'nim' => '4.33.25.2.17', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325217'],
            ['name' => 'Nia Selvia Mahdalena',                    'email' => 'nia.43325218@mhs.polines.ac.id',       'phone' => '6281326116744',  'nim' => '4.33.25.2.18', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325218'],
            ['name' => 'Reigza Nasya Pratitha Ringgiani',         'email' => 'reigza.43325219@mhs.polines.ac.id',    'phone' => '6282116626526',  'nim' => '4.33.25.2.19', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325219'],
            ['name' => 'Rizky Akbar Ardiansyah',                  'email' => 'rizky.43325220@mhs.polines.ac.id',     'phone' => '628813982499',   'nim' => '4.33.25.2.20', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325220'],
            ['name' => 'Satria Hatim Marrento',                   'email' => 'nito.43325221@mhs.polines.ac.id',      'phone' => '6282133268640',  'nim' => '4.33.25.2.21', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325221'],
            ['name' => 'Thomas Andromeda Elang Buana',            'email' => 'thomas.43325222@mhs.polines.ac.id',    'phone' => '6282113966600',  'nim' => '4.33.25.2.22', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325222'],
            ['name' => 'Ulfan Nayaka Dipta',                      'email' => 'ulfan.43325223@mhs.polines.ac.id',     'phone' => '6287776551600',  'nim' => '4.33.25.2.23', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325223'],
            ['name' => 'Yusuf Ricky Hartono',                     'email' => 'ricky.43325224@mhs.polines.ac.id',     'phone' => '6285640240434',  'nim' => '4.33.25.2.24', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325224'],
            ['name' => 'Ziyaul Falah',                            'email' => 'ziya.43325225@mhs.polines.ac.id',      'phone' => '6285156778663',  'nim' => '4.33.25.2.25', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325225'],
            // Kelas 1B (43325101–43325126)
            ['name' => 'Ahmad Fajril Falah',                      'email' => 'ahmad.43325101@mhs.polines.ac.id',     'phone' => '62895411605548', 'nim' => '4.33.25.1.01', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325101'],
            ['name' => 'Alifandi Ahmad Suryawan',                 'email' => 'alifandi.43325102@mhs.polines.ac.id',  'phone' => '6285385828282',  'nim' => '4.33.25.1.02', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325102'],
            ['name' => 'Anisa Aulia Nurusyifa',                   'email' => 'anisa.43325103@mhs.polines.ac.id',     'phone' => '6281575602347',  'nim' => '4.33.25.1.03', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325103'],
            ['name' => 'Arinda Lusyana Dewi',                     'email' => 'arinda.43325104@mhs.polines.ac.id',    'phone' => '62882008478621', 'nim' => '4.33.25.1.04', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325104'],
            ['name' => 'Bimo Radyndra Pramasara',                 'email' => 'bimo.43325105@mhs.polines.ac.id',      'phone' => '6281315301240',  'nim' => '4.33.25.1.05', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325105'],
            ['name' => "Daffa Armadhan Hidmi Ma'arif",            'email' => 'daffa.43325106@mhs.polines.ac.id',     'phone' => '6288233289358',  'nim' => '4.33.25.1.06', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325106'],
            ['name' => 'Darryll Marchiand Muchammad Nauval I',    'email' => 'darryll.43325107@mhs.polines.ac.id',   'phone' => '6281809691245',  'nim' => '4.33.25.1.07', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325107'],
            ['name' => 'Dea Cantika',                             'email' => 'dea.43325108@mhs.polines.ac.id',       'phone' => '6288232468330',  'nim' => '4.33.25.1.08', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325108'],
            ['name' => 'Galih Virgi Pramudya',                    'email' => 'galih.43325109@mhs.polines.ac.id',     'phone' => '62895422847674', 'nim' => '4.33.25.1.09', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325109'],
            ['name' => 'Inayah Cikal Nurshabrina',                'email' => 'inayah.43325110@mhs.polines.ac.id',    'phone' => '6285600372094',  'nim' => '4.33.25.1.10', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325110'],
            ['name' => 'Ivan Sigit Santoso',                      'email' => 'ivan.43325111@mhs.polines.ac.id',      'phone' => '625142655275',   'nim' => '4.33.25.1.11', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325111'],
            ['name' => 'Kurnia Angga Dimas Pratama',              'email' => 'kurnia.43325112@mhs.polines.ac.id',    'phone' => '6287802784361',  'nim' => '4.33.25.1.12', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325112'],
            ['name' => 'Lukman Arif Wicaksono',                   'email' => 'lukman.43325113@mhs.polines.ac.id',    'phone' => '6285600844632',  'nim' => '4.33.25.1.13', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325113'],
            ['name' => 'Maulidya Nurul Muharromah',               'email' => 'maulidya.43325114@mhs.polines.ac.id',  'phone' => '6285643231896',  'nim' => '4.33.25.1.14', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325114'],
            ['name' => 'Muhammad Arya Bima Surya Pratama',        'email' => 'muhammad.43325115@mhs.polines.ac.id',  'phone' => '6281319700112',  'nim' => '4.33.25.1.15', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325115'],
            ['name' => "Muhammad Dzakyy An Nafi",                 'email' => 'muhammad.43325116@mhs.polines.ac.id',  'phone' => '6285257818484',  'nim' => '4.33.25.1.16', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325116'],
            ['name' => 'Muhammad Irfan',                          'email' => 'muhammad.43325117@mhs.polines.ac.id',  'phone' => '6289503113308',  'nim' => '4.33.25.1.17', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325117'],
            ['name' => 'Muhammad Rhafi Hairy Muslim',             'email' => 'muhammad.43325118@mhs.polines.ac.id',  'phone' => '6289655067081',  'nim' => '4.33.25.1.18', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325118'],
            ['name' => 'Mukhlish Pratama Mulya',                  'email' => 'mukhlish.43325119@mhs.polines.ac.id',  'phone' => '6287790486431',  'nim' => '4.33.25.1.19', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325119'],
            ['name' => 'Rahma Aprilliana',                        'email' => 'rahma.43325120@mhs.polines.ac.id',     'phone' => '6285712708910',  'nim' => '4.33.25.1.20', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325120'],
            ['name' => 'Rasya Pandu Wicaksono',                   'email' => 'rasya.43325121@mhs.polines.ac.id',     'phone' => '6282223466896',  'nim' => '4.33.25.1.21', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325121'],
            ['name' => 'Revo Setyo Kinasih',                      'email' => 'revo.43325122@mhs.polines.ac.id',      'phone' => '6285782787696',  'nim' => '4.33.25.1.22', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325122'],
            ['name' => 'Ridho Arsyil Hakim',                      'email' => 'ridho.43325123@mhs.polines.ac.id',     'phone' => '6285932227633',  'nim' => '4.33.25.1.23', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325123'],
            ['name' => 'Rita Yulia Sari',                         'email' => 'rita.43325124@mhs.polines.ac.id',      'phone' => '6281226623431',  'nim' => '4.33.25.1.24', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325124'],
            ['name' => 'Rizky Aditya Wijaya',                     'email' => 'rizky.43325125@mhs.polines.ac.id',     'phone' => '6288215195822',  'nim' => '4.33.25.1.25', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325125'],
            ['name' => 'Vannisa Aldira Kirani',                   'email' => 'vannisa.43325126@mhs.polines.ac.id',   'phone' => '6281229808071',  'nim' => '4.33.25.1.26', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325126'],
            // Kelas 0B (43325001–43325025)
            ['name' => 'Aisa Reta Lestari',                       'email' => 'aisa.43325001@mhs.polines.ac.id',      'phone' => '6283121583141',  'nim' => '4.33.25.0.01', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325001'],
            ['name' => 'Andrian Amirudin Rahmanto',               'email' => 'andrian.43325002@mhs.polines.ac.id',   'phone' => '6289630890702',  'nim' => '4.33.25.0.02', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325002'],
            ['name' => 'Annisa Nur Farikha',                      'email' => 'annisa.43325003@mhs.polines.ac.id',    'phone' => '6281770069296',  'nim' => '4.33.25.0.03', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325003'],
            ['name' => 'Choirul Raissa Pasha',                    'email' => 'choirul.43325004@mhs.polines.ac.id',   'phone' => '62895363386904', 'nim' => '4.33.25.0.04', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325004'],
            ['name' => 'Christyan Fabiano Yehezkiel',             'email' => 'christyan.43325005@mhs.polines.ac.id', 'phone' => '6289513839132',  'nim' => '4.33.25.0.05', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325005'],
            ['name' => 'Dasi Hayu Permana',                       'email' => 'dasi.43325006@mhs.polines.ac.id',      'phone' => '6281225081066',  'nim' => '4.33.25.0.06', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325006'],
            ['name' => 'Dimas Bayu Pratama',                      'email' => 'dimas.43325007@mhs.polines.ac.id',     'phone' => '6283824837087',  'nim' => '4.33.25.0.07', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325007'],
            ['name' => 'Dzidan Adrian Firmansyah',                'email' => 'dzidan.43325008@mhs.polines.ac.id',    'phone' => '6281215789397',  'nim' => '4.33.25.0.08', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325008'],
            ['name' => 'Herdin Savira',                           'email' => 'herdin.43325009@mhs.polines.ac.id',    'phone' => '628983553540',   'nim' => '4.33.25.0.09', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325009'],
            ['name' => 'Irsyad Zaki Ramdhani',                    'email' => 'irsyad.43325010@mhs.polines.ac.id',    'phone' => '6281548237840',  'nim' => '4.33.25.0.10', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325010'],
            ['name' => 'Kavindra Vabriananda Putra Maheswara',    'email' => 'kavindra.43325011@mhs.polines.ac.id',  'phone' => '6285888554436',  'nim' => '4.33.25.0.11', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325011'],
            ['name' => 'Kyla Chavela Evangelista Wanget',         'email' => 'kyla.43325012@mhs.polines.ac.id',      'phone' => '6285759053968',  'nim' => '4.33.25.0.12', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325012'],
            ['name' => 'Mikhael Surya Adeputra',                  'email' => 'mikhael.43325013@mhs.polines.ac.id',   'phone' => '6289510656226',  'nim' => '4.33.25.0.13', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325013'],
            ['name' => 'Moh Farel Agustin',                       'email' => 'moh.43325014@mhs.polines.ac.id',       'phone' => '6289525792904',  'nim' => '4.33.25.0.14', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325014'],
            ['name' => 'Muhammad Faiq Audah',                     'email' => 'muhammad.43325015@mhs.polines.ac.id',  'phone' => '62895622396720', 'nim' => '4.33.25.0.15', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325015'],
            ['name' => 'Muhammad Faiz Firmansyah',                'email' => 'muhammad.43325016@mhs.polines.ac.id',  'phone' => '6285722209804',  'nim' => '4.33.25.0.16', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325016'],
            ['name' => 'Muhammad Faqih Ramadhan',                 'email' => 'muhammad.43325017@mhs.polines.ac.id',  'phone' => '6285225270251',  'nim' => '4.33.25.0.17', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325017'],
            ['name' => 'Nur Aini Widiyanti',                      'email' => 'nur.43325018@mhs.polines.ac.id',       'phone' => '6282136876838',  'nim' => '4.33.25.0.18', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325018'],
            ['name' => 'Pranaja Luthfi Belian',                   'email' => 'pranaja.43325019@mhs.polines.ac.id',   'phone' => '6281325466437',  'nim' => '4.33.25.0.19', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325019'],
            ['name' => 'Rafael Marcello Dwi Nanda Boro',          'email' => 'rafael.43325020@mhs.polines.ac.id',    'phone' => '6289659153030',  'nim' => '4.33.25.0.20', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325020'],
            ['name' => 'Rasya Naufal Harliandra',                 'email' => 'rasya.43325021@mhs.polines.ac.id',     'phone' => '6282138530097',  'nim' => '4.33.25.0.21', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325021'],
            ['name' => 'Riki Ulir Niam',                          'email' => 'riki.43325022@mhs.polines.ac.id',      'phone' => '6281215167617',  'nim' => '4.33.25.0.22', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325022'],
            ['name' => 'Seanirma Aida Candrawati',                'email' => 'seanirma.43325023@mhs.polines.ac.id',  'phone' => '6288233684338',  'nim' => '4.33.25.0.23', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325023'],
            ['name' => 'Tegar Bagus Satria',                      'email' => 'tegar.43325024@mhs.polines.ac.id',     'phone' => '62895342192929', 'nim' => '4.33.25.0.24', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325024'],
            ['name' => 'Victor Abdiel Kurniawan Rares',           'email' => 'victor.43325025@mhs.polines.ac.id',    'phone' => '6285869106284',  'nim' => '4.33.25.0.25', 'prodi' => 'D3 Teknik Informatika', 'pw' => '43325025'],
        ];

        foreach ($mahasiswa as $m) {
            $user = User::firstOrCreate(
                ['email' => $m['email']],
                [
                    'name'         => $m['name'],
                    'password'     => Hash::make($m['pw']),
                    'phone_number' => $m['phone'],
                ]
            );
            $user->syncRoles(['mahasiswa']);

            StudentProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'nim'           => $m['nim'],
                    'study_program' => $m['prodi'],
                ]
            );
        }
    }
}