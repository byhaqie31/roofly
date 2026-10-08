import type { LegalDocumentContent } from "./types";

// Polisi penggunaan yang dibenarkan. DRAF — mesti sepadan dengan acceptable-use.en.ts.
const doc: LegalDocumentContent = {
  title: "Penggunaan yang dibenarkan",
  summary:
    "Peraturan ini memastikan Roofly selamat dan adil untuk pemilik dan penyewa. Ia sebahagian daripada [terma perkhidmatan](/legal/terms) kami.",
  sections: [
    {
      id: "do-not",
      heading: "Perkara yang tidak boleh dilakukan",
      blocks: [
        {
          type: "ul",
          items: [
            "Membuat pemilik, penyewa, hartanah, perjanjian, invois atau rekod bayaran palsu, atau menggunakan Roofly untuk mengelirukan sesiapa.",
            "Mengganggu, mengugut, menyalahgunakan atau mendiskriminasi sesiapa, termasuk melalui aduan penyelenggaraan, komen atau mesej sokongan.",
            "Memasukkan, memuat naik atau berkongsi data peribadi orang lain tanpa hak, atau menggunakan data seseorang untuk tujuan selain mengurus penyewaan yang berkaitan.",
            "Log masuk sebagai orang lain, berkongsi akaun anda, atau cuba mengakses akaun atau data yang bukan milik anda.",
            "Mengikis, merangkak atau memuat turun Roofly secara pukal, atau menggunakan bot untuk membuat akaun atau menyertai senarai menunggu.",
            "Menyerang, membebankan, menguji atau memintas keselamatan perkhidmatan, atau memasukkan kod berniat jahat.",
            "Menyalin, menjual semula atau membuat kejuruteraan balik ke atas Roofly, atau menggunakannya untuk membina perkhidmatan pesaing.",
            "Menggunakan Roofly untuk apa-apa yang menyalahi undang-undang, termasuk penipuan atau pengubahan wang haram.",
          ],
        },
      ],
    },
    {
      id: "reporting",
      heading: "Melaporkan masalah",
      blocks: [
        {
          type: "p",
          text: "Jika anda melihat sesuatu yang melanggar peraturan ini, atau menemui kelemahan keselamatan, beritahu kami melalui Bantuan & maklum balas dalam aplikasi atau menggunakan butiran di bawah. Jangan uji kelemahan keselamatan melebihi apa yang perlu untuk melaporkannya.",
        },
        { type: "contact", channel: "general" },
      ],
    },
    {
      id: "what-we-may-do",
      heading: "Tindakan yang boleh kami ambil",
      blocks: [
        { type: "p", text: "Jika peraturan ini dilanggar, bergantung pada tahap keseriusannya, kami boleh:" },
        {
          type: "ul",
          items: [
            "menghubungi anda dan meminta anda berhenti;",
            "mengeluarkan amaran rasmi pada akaun anda;",
            "membuang kandungan atau rekod yang berkenaan;",
            "menggantung atau menutup akaun anda;",
            "melaporkan perkara itu kepada pihak berkuasa apabila dikehendaki atau dibenarkan oleh undang-undang.",
          ],
        },
        {
          type: "p",
          text: "Jika munasabah dan sah di sisi undang-undang, kami akan memaklumkan sebabnya dan memberi anda peluang untuk menjawab.",
        },
      ],
    },
  ],
};

export default doc;
