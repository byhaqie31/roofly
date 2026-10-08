import type { LegalDocumentContent } from "./types";

// Notis privasi di bawah Akta Perlindungan Data Peribadi 2010 (dipinda 2024).
// DRAF untuk semakan undang-undang — mesti sepadan dengan privacy.en.ts
// (id seksyen yang sama; registry.test.ts menyemaknya).
const doc: LegalDocumentContent = {
  title: "Notis privasi",
  summary:
    "Notis ini menerangkan data peribadi yang disimpan oleh Roofly, sebab kami menyimpannya, pihak yang menerimanya dan cara anda boleh mengakses atau membetulkannya. Ia terpakai kepada roofly.my dan subdomainnya.",
  sections: [
    {
      id: "who-we-are",
      heading: "Siapa kami",
      blocks: [
        {
          type: "p",
          text: "Roofly ialah perkhidmatan pengurusan sewa untuk pemilik rumah sewa di Malaysia dan penyewa mereka. Roofly ialah produk Axel Nova Ventures, yang mengendalikan perkhidmatan ini. Dalam notis ini, \"kami\" bermaksud Axel Nova Ventures.",
        },
        { type: "contact", channel: "privacy" },
      ],
    },
    {
      id: "our-roles",
      heading: "Dua peranan kami",
      blocks: [
        {
          type: "p",
          text: "Kami ialah pengawal data bagi data yang kami kumpul untuk kegunaan kami sendiri: akaun pemilik, butiran log masuk penyewa, senarai menunggu, mesej sokongan, analitik dan log keselamatan.",
        },
        {
          type: "p",
          text: "Apabila pemilik menambah penyewa dan merekodkan penyewaan (butiran penyewa, perjanjian, invois sewa, bayaran dan aduan penyelenggaraan), pemilik yang menentukan tujuan dan cara data itu digunakan. Bagi data tersebut, pemilik ialah pengawal data dan kami bertindak sebagai pemproses data bagi pihak pemilik. Penyewa yang ingin tahu cara pemilik menggunakan data mereka boleh juga bertanya terus kepada pemilik.",
        },
      ],
    },
    {
      id: "data-we-hold",
      heading: "Data peribadi yang kami simpan",
      blocks: [
        { type: "p", text: "Bergantung pada cara anda menggunakan Roofly, kami menyimpan data berikut." },
        {
          type: "ul",
          items: [
            "Pemilik: nama, alamat e-mel, nombor telefon, kata laluan (disimpan hanya dalam bentuk cincang sehala), nama perniagaan dan empat digit terakhir akaun bank jika anda menambahnya, pelan, keutamaan, serta hartanah, unit dan pemilik bersama (nama dan bahagian pemilikan) yang anda rekodkan, termasuk butiran hak milik, harga belian dan nilaian yang anda pilih untuk masukkan.",
            "Pemilik yang log masuk dengan Google: ID akaun Google, nama, alamat e-mel dan pautan ke gambar profil Google anda.",
            "Penyewa: nama, alamat e-mel, nombor telefon, cincangan kata laluan, nombor MyKad, tarikh lahir, warganegara, pekerjaan, majikan dan pendapatan bulanan jika diberikan, kenalan kecemasan (nama, nombor telefon dan hubungan), serta rekod penyewaan yang berkaitan dengan anda: perjanjian, invois sewa, rekod bayaran, aduan penyelenggaraan dan komen.",
            "Senarai menunggu: alamat e-mel anda, tarikh anda menyertai, dan sama ada anda kemudiannya menerima jemputan atau mendaftar.",
            "Mesej sokongan: nama, alamat e-mel, jenis akaun, mesej yang anda hantar, halaman tempat anda menghantarnya dan butiran pelayar anda, serta nota dalaman kami tentang mesej itu.",
            "Analitik: paparan halaman dan beberapa tindakan di halaman awam kami, seperti yang diterangkan di bawah \"Analitik dan storan pelayar\".",
            "Log keselamatan dan aktiviti: sesi log masuk (alamat IP dan butiran pelayar), rekod perubahan pada akaun dan rekod penyewaan, serta rekod tindakan kakitangan kami, termasuk alamat IP mereka.",
          ],
        },
        {
          type: "p",
          text: "Bayaran dalam Roofly kini hanyalah simulasi. Kami tidak mengumpul nombor kad atau butiran perbankan dalam talian.",
        },
      ],
    },
    {
      id: "sources",
      heading: "Dari mana data diperoleh",
      blocks: [
        {
          type: "ul",
          items: [
            "Daripada anda, apabila anda menyertai senarai menunggu, membuat akaun, mengisi profil, menggunakan aplikasi atau menghubungi kami.",
            "Daripada pemilik, apabila mereka menjemput anda sebagai penyewa atau merekodkan penyewaan anda.",
            "Daripada Google, apabila pemilik memilih untuk log masuk dengan Google.",
            "Daripada pelayar anda, apabila anda melawat halaman awam kami atau log masuk (lihat \"Analitik dan storan pelayar\").",
          ],
        },
      ],
    },
    {
      id: "purposes",
      heading: "Tujuan kami menggunakannya",
      blocks: [
        {
          type: "ul",
          items: [
            "Untuk membuat dan mengendalikan akaun anda dan membolehkan anda log masuk.",
            "Untuk membolehkan pemilik mengurus hartanah, penyewaan, perjanjian, invois sewa, rekod bayaran dan aduan penyelenggaraan, dan membolehkan penyewa melihat serta mengurus penyewaan mereka sendiri.",
            "Untuk menghantar e-mel perkhidmatan, seperti jemputan, set semula kata laluan, e-mel senarai menunggu dan alu-aluan, serta balasan kepada mesej anda.",
            "Untuk menjawab mesej sokongan dan menambah baik Roofly berdasarkan maklum balas.",
            "Untuk memahami cara pelawat menemui dan menggunakan halaman awam kami.",
            "Untuk memastikan perkhidmatan selamat, menyiasat penyalahgunaan dan menyimpan jejak audit tindakan kakitangan.",
            "Untuk memenuhi kewajipan undang-undang kami.",
          ],
        },
        {
          type: "p",
          text: "Kami tidak menjual data peribadi dan tidak menggunakannya untuk pengiklanan pihak ketiga.",
        },
      ],
    },
    {
      id: "obligatory",
      heading: "Adakah anda wajib memberikan data ini?",
      blocks: [
        {
          type: "p",
          text: "Nama dan alamat e-mel anda wajib untuk membuat akaun, dan alamat e-mel wajib untuk menyertai senarai menunggu. Tanpanya, kami tidak dapat menyediakan akaun atau menghubungi anda.",
        },
        {
          type: "p",
          text: "Penyewa diminta memberikan nombor telefon, nombor MyKad dan kenalan kecemasan sebelum boleh menggunakan aplikasi penyewa, kerana pemilik memerlukannya untuk mengurus penyewaan. Jika anda tidak memberikannya, anda tidak akan dapat menggunakan aplikasi penyewa. Maklumat lain, seperti tarikh lahir, pekerjaan, majikan dan pendapatan, adalah secara sukarela.",
        },
      ],
    },
    {
      id: "tracking",
      heading: "Analitik dan storan pelayar",
      blocks: [
        {
          type: "p",
          text: "Kami menjalankan analitik kami sendiri. Ia hanya berjalan di halaman awam kami: halaman utama dan halaman akan datang, demo, halaman log masuk dan pendaftaran, serta halaman perundangan ini. Ia tidak pernah berjalan di dalam bahagian pemilik, penyewa atau admin aplikasi.",
        },
        {
          type: "p",
          text: "Di halaman tersebut kami merekodkan paparan halaman dan beberapa tindakan (membuka demo, menyertai senarai menunggu, mendaftar dan menghantar maklum balas demo), bersama alamat halaman, laman yang merujuk anda, tag kempen dalam pautan yang anda ikuti, butiran pelayar anda dan bentuk alamat IP anda yang telah dikaburkan (dicincang). Untuk menghubungkan lawatan, kami menyimpan ID pelawat rawak dalam storan setempat pelayar anda.",
        },
        {
          type: "p",
          text: "Kami tidak menggunakan analitik pihak ketiga atau kuki pengiklanan. Satu-satunya kuki yang kami tetapkan adalah untuk memastikan anda kekal log masuk, melindungi borang daripada pemalsuan permintaan rentas tapak dan mengingati bahasa pilihan anda. Pelayar anda juga menyimpan pilihan tema anda. Anda boleh memadam semua ini dalam tetapan pelayar anda pada bila-bila masa.",
        },
      ],
    },
    {
      id: "sharing",
      heading: "Pihak yang menerima data",
      blocks: [
        {
          type: "p",
          text: "Kami hanya berkongsi data peribadi dengan kelas pihak ketiga berikut, dan setakat yang diperlukan oleh setiap pihak:",
        },
        {
          type: "ul",
          items: [
            // TODO(legal): sahkan peranan Cloudflare (DNS sahaja, atau proksi/CDN) dan penyedia e-mel produksi.
            "Penyedia perkhidmatan yang mengendalikan Roofly untuk kami: penyedia hos pelayan kami (Hostinger), penyedia nama domain dan rangkaian kami (Cloudflare) dan penyedia penghantaran e-mel kami.",
            "Google, apabila pemilik memilih log masuk dengan Google. Di halaman log masuk yang menawarkan log masuk Google, pelayar anda memuatkan skrip log masuk Google.",
            "Pihak lain dalam penyewaan: pemilik melihat butiran penyewa yang mereka urus, dan penyewa melihat perjanjian, invois dan aduan mereka sendiri, termasuk nama pemilik.",
            "Pihak berkuasa, mahkamah atau pengawal selia, apabila dikehendaki oleh undang-undang.",
            // TODO(legal): klausa pemindahan perniagaan standard; sahkan sama ada mahu dikekalkan.
            "Pembeli atau pengganti perniagaan, jika Roofly dijual atau disusun semula, dengan perlindungan yang sama.",
          ],
        },
        {
          type: "p",
          text: "Apabila bayaran sebenar diperkenalkan, gerbang pembayaran akan memprosesnya. Kami akan mengemas kini notis ini sebelum itu berlaku.",
        },
      ],
    },
    {
      id: "transfers",
      heading: "Tempat data anda disimpan",
      blocks: [
        {
          // TODO(legal): sahkan lokasi pusat data VPS Hostinger dan sama ada data peribadi dipindahkan ke luar Malaysia.
          type: "p",
          text: "Pelayan kami dikendalikan oleh penyedia hos kami. Sesetengah penyedia perkhidmatan kami mungkin memproses data di luar Malaysia. Jika data peribadi dipindahkan ke luar Malaysia, kami mengambil langkah untuk memastikan ia dilindungi pada tahap yang setanding dengan Akta Perlindungan Data Peribadi 2010.",
        },
      ],
    },
    {
      id: "retention",
      heading: "Tempoh kami menyimpannya",
      blocks: [
        {
          type: "p",
          text: "Apabila akaun, hartanah, unit, perjanjian, invois atau aduan penyelenggaraan dipadam dalam Roofly, ia disembunyikan dahulu dan tidak terus dihapuskan, supaya kesilapan boleh dibetulkan dan rekod kekal konsisten. Entri senarai menunggu, mesej sokongan, peristiwa analitik dan log aktiviti disimpan sehingga kami membuangnya.",
        },
        {
          // TODO(legal): tetapkan tempoh penyimpanan bagi setiap kategori dan cara penghapusan kekal dilakukan.
          type: "p",
          text: "Kami menyimpan data peribadi hanya selama yang diperlukan untuk tujuan dalam notis ini atau seperti yang dikehendaki undang-undang, dan kemudian memadam atau menjadikannya tanpa nama.",
        },
      ],
    },
    {
      id: "rights",
      heading: "Hak anda",
      blocks: [
        { type: "p", text: "Di bawah Akta Perlindungan Data Peribadi 2010, anda boleh:" },
        {
          type: "ul",
          items: [
            "Meminta akses kepada data peribadi yang kami simpan tentang anda dan salinannya.",
            "Meminta kami membetulkan data peribadi yang tidak tepat, tidak lengkap, mengelirukan atau lapuk. Kebanyakannya boleh juga anda betulkan sendiri di halaman profil atau tetapan.",
            "Menarik balik persetujuan anda terhadap pemprosesan, atau meminta kami mengehadkannya, termasuk meminta kami tidak menghantar e-mel yang tidak anda minta. Ini mungkin bermakna kami tidak dapat terus menyediakan perkhidmatan.",
            "Meminta data anda dalam format yang biasa digunakan dan boleh dibaca mesin supaya anda boleh memindahkannya ke perkhidmatan lain (kemudahalihan data).",
          ],
        },
        {
          type: "p",
          text: "Untuk membuat permintaan, e-mel kenalan privasi kami di bawah, atau hantar mesej melalui Bantuan & maklum balas dalam aplikasi. Kami mungkin perlu mengesahkan identiti anda dahulu dan akan membalas dalam tempoh yang dibenarkan oleh undang-undang. Jika permintaan anda berkaitan data penyewaan yang dikawal oleh pemilik, kami akan bekerjasama dengan pemilik itu untuk menjawabnya.",
        },
        { type: "contact", channel: "privacy" },
      ],
    },
    {
      id: "staff-access",
      heading: "Siapa di Roofly yang boleh melihatnya",
      blocks: [
        {
          type: "p",
          text: "Kakitangan admin kami hanya melihat apa yang diperlukan untuk mengurus akaun dan memberi sokongan. Bagi pemilik: nama, alamat e-mel, nombor telefon, nama perniagaan, pelan, status akaun, bilangan rekod serta nama, bandar dan negeri setiap hartanah. Bagi penyewa, yang butirannya milik pemilik: nama yang dipendekkan (seperti \"Aminah Y.\"), alamat e-mel dan nombor telefon, status mereka, dan pemilik, hartanah serta unit yang dikaitkan dengan mereka. Kakitangan juga melihat e-mel senarai menunggu bersama sejarah lawatan di halaman awam kami, dan mesej sokongan yang dihantar kepada kami.",
        },
        {
          type: "p",
          text: "Kakitangan admin tidak melihat nama penuh penyewa, nombor MyKad, butiran peribadi lain, kenalan kecemasan, alamat jalan, jumlah sewa, invois atau bayaran. Akses kakitangan dihadkan mengikut peranan, dan tindakan kakitangan direkodkan.",
        },
      ],
    },
    {
      id: "security",
      heading: "Keselamatan dan pelanggaran data",
      blocks: [
        {
          type: "p",
          text: "Kami melindungi data peribadi dengan langkah teknikal dan organisasi, termasuk sambungan yang disulitkan, kata laluan yang dicincang, akses berasaskan peranan dan log aktiviti, dan pemproses data kami juga wajib melindunginya. Jika berlaku pelanggaran data peribadi, kami akan memaklumkan Pesuruhjaya Perlindungan Data Peribadi dan orang yang terjejas apabila dikehendaki oleh undang-undang.",
        },
      ],
    },
    {
      id: "changes",
      heading: "Perubahan dan bahasa",
      blocks: [
        {
          type: "p",
          text: "Kami akan mengemas kini notis ini apabila cara kami menggunakan data peribadi berubah, dan memaparkan nombor versi serta tarikh berkuat kuasa yang baharu di bahagian atas. Bagi perubahan penting, kami juga akan memaklumkan pemegang akaun melalui e-mel atau dalam aplikasi.",
        },
        {
          // TODO(legal): sahkan versi bahasa yang diguna pakai.
          type: "p",
          text: "Notis ini tersedia dalam bahasa Inggeris dan Bahasa Malaysia. Jika terdapat percanggahan antara kedua-dua versi, versi bahasa Inggeris akan diguna pakai.",
        },
      ],
    },
    {
      id: "contact",
      heading: "Hubungi kami",
      blocks: [
        {
          type: "p",
          text: "Untuk sebarang soalan tentang notis ini atau data peribadi anda, hubungi kami menggunakan butiran di bawah.",
        },
        { type: "contact", channel: "privacy" },
      ],
    },
  ],
};

export default doc;
