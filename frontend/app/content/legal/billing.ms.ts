import type { LegalDocumentContent } from "./types";

// Polisi bil dan bayaran balik. DRAF — mesti sepadan dengan billing.en.ts.
// TODO(legal): senarai seksyen yang perlu ditambah sebelum bil bermula ada dalam billing.en.ts.
const doc: LegalDocumentContent = {
  title: "Polisi bil dan bayaran balik",
  summary: "Roofly percuma sepanjang tempoh beta. Tiada sesiapa dikenakan bayaran, dan kami tidak mengumpul sebarang butiran pembayaran.",
  sections: [
    {
      id: "today",
      heading: "Percuma sepanjang beta",
      blocks: [
        {
          type: "p",
          text: "Sepanjang Roofly dalam beta, setiap akaun boleh menggunakannya tanpa kos. Kami tidak meminta butiran kad atau bank, dan tiada langganan dikenakan atau diperbaharui.",
        },
      ],
    },
    {
      id: "plans",
      heading: "Pelan dan harga yang dirancang",
      blocks: [
        {
          type: "p",
          text: "Selepas beta, pelan Roofly akan berbeza mengikut bilangan unit yang boleh anda urus. Ini ialah harga bulanan yang dirancang dalam ringgit Malaysia:",
        },
        { type: "plans" },
        {
          type: "p",
          text: "Pelan percuma akan kekal selepas beta. Harga yang dirancang mungkin berubah sebelum bil bermula.",
        },
      ],
    },
    {
      id: "before-billing",
      heading: "Sebelum sebarang bil bermula",
      blocks: [
        {
          type: "p",
          text: "Sebelum kami mengenakan bayaran kepada sesiapa, kami akan menerbitkan di halaman ini terma penuh bagi pembaharuan, pembatalan, penurunan pelan dan bayaran balik, kaedah pembayaran yang kami terima dan cara bayaran diproses. Kami akan memaklumkan pemegang akaun lebih awal, dan tiada sesiapa akan dipindahkan ke pelan berbayar tanpa memilihnya.",
        },
      ],
    },
    {
      id: "rent",
      heading: "Sewa tidak dibilkan oleh Roofly",
      blocks: [
        {
          type: "p",
          text: "Invois sewa dalam Roofly adalah antara pemilik dan penyewa. Bayaran dalam Roofly kini hanyalah simulasi, dan Roofly tidak mengutip atau memegang wang sewa. Lihat [terma perkhidmatan](/legal/terms) kami untuk maklumat lanjut.",
        },
      ],
    },
    {
      id: "contact",
      heading: "Soalan",
      blocks: [
        { type: "p", text: "Untuk soalan tentang pelan atau bil, hubungi kami menggunakan butiran di bawah." },
        { type: "contact", channel: "general" },
      ],
    },
  ],
};

export default doc;
