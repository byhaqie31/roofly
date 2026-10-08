import type { LegalDocumentContent } from "./types";

// Terma perkhidmatan. DRAF untuk semakan undang-undang — mesti sepadan dengan
// terms.en.ts (id seksyen yang sama).
const doc: LegalDocumentContent = {
  title: "Terma perkhidmatan",
  summary:
    "Terma ini ialah perjanjian antara anda dan Axel Nova Ventures untuk menggunakan Roofly. Sila bacanya bersama [notis privasi](/legal/privacy) dan [polisi penggunaan yang dibenarkan](/legal/acceptable-use) kami.",
  sections: [
    {
      id: "about",
      heading: "Tentang terma ini",
      blocks: [
        {
          type: "p",
          text: "Roofly disediakan oleh Axel Nova Ventures (\"kami\"). Dengan membuat akaun, menerima jemputan atau menggunakan Roofly, anda bersetuju dengan terma ini. Menerimanya dalam talian adalah sama mengikat seperti menandatanganinya di atas kertas.",
        },
        {
          // TODO(legal): sahkan syarat kelayakan (umur, keupayaan, Malaysia sahaja atau tidak).
          type: "p",
          text: "Anda mesti berumur sekurang-kurangnya 18 tahun dan berupaya memasuki kontrak yang mengikat untuk menggunakan Roofly. Jika anda menggunakan Roofly untuk perniagaan, anda mengesahkan bahawa anda diberi kuasa untuk menerima terma ini bagi pihaknya.",
        },
      ],
    },
    {
      id: "accounts",
      heading: "Akaun dan peranan",
      blocks: [
        {
          type: "ul",
          items: [
            "Pemilik mendaftar sendiri, dengan alamat e-mel dan kata laluan atau dengan log masuk Google, dan mengurus hartanah, penyewa dan penyewaan mereka.",
            "Penyewa hanya boleh menyertai apabila dijemput oleh pemilik. Penyewa boleh melihat dan mengurus penyewaan mereka sendiri: perjanjian, invois, bayaran dan aduan penyelenggaraan.",
            "Anda bertanggungjawab menjaga keselamatan butiran log masuk anda dan atas apa yang berlaku di bawah akaun anda. Maklumkan kami segera jika anda fikir orang lain telah menggunakannya.",
            "Butiran yang anda berikan mestilah tepat, dan anda perlu memastikannya terkini.",
          ],
        },
      ],
    },
    {
      id: "plans",
      heading: "Pelan dan harga",
      blocks: [
        {
          type: "p",
          text: "Pelan Roofly berbeza mengikut bilangan unit yang boleh anda urus. Ini ialah harga bulanan yang dirancang:",
        },
        { type: "plans" },
        {
          type: "p",
          text: "Roofly percuma sepanjang tempoh beta dan tiada sesiapa dikenakan bayaran. Pelan percuma akan kekal selepas beta. Sebelum sebarang bil bermula, kami akan menerbitkan terma pembaharuan, pembatalan, penurunan pelan dan bayaran balik dalam [polisi bil dan bayaran balik](/legal/billing) kami dan memaklumkan pemegang akaun lebih awal.",
        },
      ],
    },
    {
      id: "what-roofly-is-not",
      heading: "Apa yang Roofly bukan",
      blocks: [
        {
          type: "p",
          text: "Roofly ialah alat yang membantu pemilik dan penyewa menyimpan rekod dan berhubung. Roofly bukan:",
        },
        {
          type: "ul",
          items: [
            "pihak dalam mana-mana penyewaan. Penyewaan adalah antara pemilik dan penyewa, dan ditadbir oleh perjanjian penyewaan mereka serta undang-undang am Malaysia, termasuk Akta Kontrak 1950;",
            "ejen hartanah, dan tidak mencari, menyaring atau mengesyorkan penyewa atau hartanah;",
            "firma guaman, dan tiada apa-apa dalam Roofly ialah nasihat undang-undang;",
            "penasihat cukai, dan tiada apa-apa dalam Roofly ialah nasihat cukai.",
          ],
        },
      ],
    },
    {
      id: "agreements",
      heading: "Perjanjian penyewaan dan setem",
      blocks: [
        {
          type: "p",
          text: "Roofly membantu pemilik merekodkan terma penyewaan dan menghantarnya kepada penyewa untuk disemak dan diterima. Perkataan, medan dan struktur yang ditawarkan oleh Roofly hanyalah untuk kemudahan, bukan nasihat undang-undang, dan mungkin tidak sesuai untuk setiap penyewaan. Dapatkan nasihat undang-undang bebas jika anda tidak pasti.",
        },
        {
          type: "p",
          text: "Apabila penyewa menerima perjanjian dalam Roofly, Roofly merekodkan penerimaan itu. Sama ada dan bagaimana perjanjian itu mengikat pemilik dan penyewa adalah urusan antara mereka. Penyeteman perjanjian penyewaan dengan LHDN, dan pembayaran duti setem, adalah tanggungjawab pemilik dan penyewa, bukan Roofly.",
        },
      ],
    },
    {
      id: "reports",
      heading: "Laporan dan angka cukai",
      blocks: [
        {
          type: "p",
          text: "Laporan, ringkasan pendapatan dan angka cukai keuntungan harta tanah (RPGT) ialah anggaran berdasarkan data yang anda masukkan dan peraturan yang dipermudahkan. Ia bukan nasihat cukai dan mungkin tidak tepat untuk situasi anda. Semak dengan penasihat cukai atau LHDN sebelum bergantung padanya.",
        },
      ],
    },
    {
      id: "payments",
      heading: "Bayaran",
      blocks: [
        {
          type: "p",
          text: "Bayaran dalam Roofly kini hanyalah simulasi: tiada wang sebenar bergerak melalui Roofly, dan bayaran yang ditanda sebagai dibayar dalam aplikasi hanyalah rekod. Pemilik juga boleh merekodkan bayaran yang diterima di luar Roofly.",
        },
        {
          type: "p",
          text: "Apabila bayaran dalam talian sebenar dilancarkan, ia akan diproses oleh gerbang pembayaran pihak ketiga di bawah termanya sendiri. Roofly tidak akan memegang wang sewa. Kami akan mengemas kini terma ini sebelum itu berlaku.",
        },
      ],
    },
    {
      id: "owner-responsibilities",
      heading: "Tanggungjawab pemilik",
      blocks: [
        {
          type: "ul",
          items: [
            "Anda mesti mempunyai hak untuk memberikan data peribadi penyewa anda kepada kami, dan untuk membenarkan kami memprosesnya bagi pihak anda. Bagi data penyewaan yang anda masukkan, anda ialah pengawal data di bawah Akta Perlindungan Data Peribadi 2010 dan kami memprosesnya bagi pihak anda, seperti yang diterangkan dalam [notis privasi](/legal/privacy) kami.",
            "Anda mesti memaklumkan penyewa bahawa anda menggunakan Roofly untuk mengurus penyewaan mereka, dan mengendalikan permintaan mereka tentang data mereka.",
            "Anda bertanggungjawab atas ketepatan hartanah, penyewaan, invois dan rekod bayaran yang anda buat, dan untuk mematuhi undang-undang yang terpakai kepada penyewaan anda.",
          ],
        },
      ],
    },
    {
      id: "acceptable-use",
      heading: "Penggunaan yang dibenarkan",
      blocks: [
        {
          type: "p",
          text: "Gunakan Roofly secara sah dan adil. Jangan buat penyewa atau hartanah palsu, mengganggu atau menyalahgunakan sesiapa melalui aduan atau komen, memasukkan data orang lain tanpa hak, atau mengikis, membebankan atau menyerang perkhidmatan. Peraturan penuh, dan tindakan yang boleh kami ambil jika ia dilanggar, terdapat dalam [polisi penggunaan yang dibenarkan](/legal/acceptable-use) kami.",
        },
      ],
    },
    {
      id: "content-and-ip",
      heading: "Data anda dan harta intelek kami",
      blocks: [
        {
          type: "p",
          text: "Data yang anda masukkan ke dalam Roofly kekal milik anda. Anda membenarkan kami menyimpan dan memprosesnya hanya untuk menyediakan dan menambah baik perkhidmatan dan seperti yang diterangkan dalam notis privasi kami.",
        },
        {
          type: "p",
          text: "Roofly sendiri (perisian, reka bentuk, nama dan logonya) adalah milik Axel Nova Ventures. Anda boleh menggunakannya seperti yang dibenarkan oleh terma ini, tetapi tidak boleh menyalin, menjual semula atau membuat kejuruteraan balik ke atasnya. Jika anda menghantar maklum balas atau idea kepada kami, kami boleh menggunakannya tanpa berhutang apa-apa kepada anda.",
        },
      ],
    },
    {
      id: "availability",
      heading: "Ketersediaan dan beta",
      blocks: [
        {
          type: "p",
          text: "Kami berusaha memastikan Roofly sentiasa berjalan, tetapi ia kadangkala mungkin tidak tersedia kerana penyelenggaraan, kemas kini atau sebab di luar kawalan kami. Kami boleh mengubah, menambah atau membuang ciri. Sesetengah ciri ditanda sebagai akan datang dan belum tersedia.",
        },
        {
          type: "p",
          text: "Sepanjang tempoh beta, Roofly disediakan \"seadanya\" dan \"mengikut ketersediaan\", dan mungkin mengandungi ralat. Simpan salinan anda sendiri bagi apa-apa yang penting.",
        },
      ],
    },
    {
      id: "liability",
      heading: "Liabiliti kami",
      blocks: [
        {
          // TODO(legal): draf had liabiliti yang menghormati Akta Pelindungan Pengguna 1999 dan peraturan terma tidak adilnya; ini hanya pemegang tempat.
          type: "p",
          text: "Tiada apa-apa dalam terma ini mengehadkan hak anda di bawah Akta Pelindungan Pengguna 1999 atau undang-undang lain yang tidak boleh dikecualikan. Tertakluk kepada itu, kami tidak bertanggungjawab atas kerugian yang disebabkan oleh penyewaan itu sendiri, oleh tindakan pemilik dan penyewa, oleh keputusan yang dibuat berdasarkan anggaran atau rekod dalam Roofly, atau oleh peristiwa di luar kawalan munasabah kami.",
        },
      ],
    },
    {
      id: "ending",
      heading: "Menamatkan akaun dan mengeksport data",
      blocks: [
        {
          type: "p",
          text: "Anda boleh berhenti menggunakan Roofly pada bila-bila masa. Untuk menutup akaun anda, hubungi kami. Pemilik boleh memuat turun laporan tahunan mereka sebagai fail CSV dari halaman Laporan, dan sesiapa sahaja boleh meminta salinan data mereka seperti yang diterangkan dalam notis privasi kami.",
        },
        {
          type: "p",
          text: "Kami boleh menggantung atau menutup akaun yang melanggar terma ini atau polisi penggunaan yang dibenarkan, atau apabila dikehendaki oleh undang-undang. Jika munasabah, kami akan memaklumkan anda dahulu dan memberi peluang untuk mengeksport data anda.",
        },
      ],
    },
    {
      id: "changes",
      heading: "Perubahan pada terma ini",
      blocks: [
        {
          type: "p",
          text: "Kami boleh mengemas kini terma ini. Nombor versi dan tarikh berkuat kuasa di bahagian atas menunjukkan versi semasa. Bagi perubahan penting, kami akan memaklumkan pemegang akaun melalui e-mel atau dalam aplikasi sebelum ia berkuat kuasa. Jika anda terus menggunakan Roofly selepas itu, terma baharu terpakai.",
        },
      ],
    },
    {
      id: "law",
      heading: "Undang-undang yang mentadbir",
      blocks: [
        {
          // TODO(legal): sahkan undang-undang yang mentadbir, bidang kuasa dan bahasa yang diguna pakai.
          type: "p",
          text: "Terma ini ditadbir oleh undang-undang Malaysia, dan mahkamah Malaysia mempunyai bidang kuasa. Terma ini tersedia dalam bahasa Inggeris dan Bahasa Malaysia. Jika terdapat percanggahan antara kedua-dua versi, versi bahasa Inggeris akan diguna pakai.",
        },
      ],
    },
    {
      id: "contact",
      heading: "Hubungi kami",
      blocks: [{ type: "contact", channel: "general" }],
    },
  ],
};

export default doc;
