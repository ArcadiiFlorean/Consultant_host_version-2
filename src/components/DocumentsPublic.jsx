import React, { useState, useEffect } from 'react';

const DocumentsPublic = () => {
  const [documents, setDocuments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [selectedCategory, setSelectedCategory] = useState('all');

  // Mock data pentru fallback
  const mockDocuments = [
    {
      id: 1,
      title: "Ghidul Complet al Alăptării",
      description: "Un ghid detaliat pentru mamele noi despre toate aspectele alăptării, de la primul început până la înțărcare.",
      category: "ghiduri",
      file_type: "application/pdf",
      file_size: 2048576,
      downloads_count: 156,
      is_free: true,
      is_featured: true,
      price: null
    },
    {
      id: 2,
      title: "Checklist pentru Primele 6 Luni",
      description: "O listă detaliată cu tot ce trebuie să știi și să faci în primele 6 luni de alăptare.",
      category: "checklist",
      file_type: "application/pdf",
      file_size: 512000,
      downloads_count: 89,
      is_free: true,
      is_featured: false,
      price: null
    },
    {
      id: 3,
      title: "Formular de Monitorizare Alăptare",
      description: "Formular printabil pentru a urmări programul de alăptare și dezvoltarea copilului.",
      category: "formulare",
      file_type: "application/pdf",
      file_size: 256000,
      downloads_count: 234,
      is_free: true,
      is_featured: false,
      price: null
    }
  ];

  // Categorii disponibile
  const categories = [
    { value: 'all', label: 'Toate Documentele', icon: '📁' },
    { value: 'ghiduri', label: 'Ghiduri', icon: '📖' },
    { value: 'formulare', label: 'Formulare', icon: '📝' },
    { value: 'resurse', label: 'Resurse', icon: '💎' },
    { value: 'checklist', label: 'Checklist-uri', icon: '✅' },
    { value: 'general', label: 'General', icon: '📋' }
  ];

  // Configurare API
  const API_CONFIG = {
    // Pentru development cu server PHP local
    development: 'http://localhost/Breastfeeding-Help-Support/admin/documents_public_api.php',
    // Pentru production
    production: '/Breastfeeding-Help-Support/admin/documents_public_api.php',
    // Sau folosește direct URL-ul serverului tău
    direct: 'https://your-domain.com/Breastfeeding-Help-Support/admin/documents_public_api.php'
  };

  const isDevelopment = process.env.NODE_ENV === 'development' || window.location.hostname === 'localhost';
  
  // Încarcă documentele
  useEffect(() => {
    loadDocuments();
  }, []);

  const loadDocuments = async () => {
    try {
      setLoading(true);
      setError(null);

      // Încearcă să încarce de la API
      let apiUrl = isDevelopment ? API_CONFIG.development : API_CONFIG.production;
      
      // Dacă vrei să testezi cu URL direct, decomentează:
      // apiUrl = API_CONFIG.direct;

      console.log(`🔧 Încarcă documente de la: ${apiUrl}`);

      const response = await fetch(apiUrl, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        // Pentru development cu CORS
        mode: 'cors',
      });
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        const text = await response.text();
        console.error('Response is not JSON:', text.substring(0, 200));
        throw new Error('Server returned HTML instead of JSON');
      }

      const result = await response.json();
      console.log('📊 API Response:', result);
      
      if (result.success) {
        const documentsData = Array.isArray(result.data) ? result.data : [];
        setDocuments(documentsData);
        console.log(`✅ Încărcate ${documentsData.length} documente din API`);
      } else {
        throw new Error(result.error || 'Eroare la încărcarea documentelor');
      }
    } catch (err) {
      console.error('❌ Error loading documents:', err);
      
      // Fallback la mock data în caz de eroare
      console.log('🔄 Fallback la mock data...');
      setDocuments(mockDocuments);
      
      let errorMessage = `API Error: ${err.message}. Folosind date demo.`;
      setError(errorMessage);
      
      // Auto-clear error după 5 secunde
      setTimeout(() => setError(null), 5000);
    } finally {
      setLoading(false);
    }
  };

  // Funcția de download
  const handleDownload = (documentId, documentTitle) => {
    console.log(`📥 Descărcare document: ${documentTitle} (ID: ${documentId})`);
    
    if (!documentId || !documentTitle) {
      showNotification('❌ Eroare: Document invalid!', 'error');
      return;
    }

    // URL pentru download
    let downloadUrl;
    if (isDevelopment) {
      downloadUrl = `http://localhost/Breastfeeding-Help-Support/admin/download_document.php?id=${encodeURIComponent(documentId)}`;
    } else {
      downloadUrl = `/Breastfeeding-Help-Support/admin/download_document.php?id=${encodeURIComponent(documentId)}`;
    }
    
    try {
      const downloadWindow = window.open(downloadUrl, '_blank', 'noopener,noreferrer');
      
      if (!downloadWindow) {
        window.location.href = downloadUrl;
      }
      
      showNotification(`📥 Descărcarea documentului "${documentTitle}" a început!`, 'success');
    } catch (err) {
      console.error('Download error:', err);
      showNotification('❌ Eroare la descărcare. Încearcă din nou.', 'error');
    }
  };

  // Filtrează documentele după categorie
  const filteredDocuments = selectedCategory === 'all' 
    ? documents 
    : documents.filter(doc => doc.category === selectedCategory);

  // Funcție pentru notificări
  const showNotification = (message, type = 'info') => {
    const existingNotification = document.querySelector('.custom-notification');
    if (existingNotification) {
      existingNotification.remove();
    }

    const notification = document.createElement('div');
    notification.className = `custom-notification fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm ${
      type === 'success' ? 'bg-green-500' : 
      type === 'error' ? 'bg-red-500' : 'bg-blue-500'
    } text-white transform translate-x-full transition-transform duration-300`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
      notification.style.transform = 'translateX(0)';
    }, 100);
    
    setTimeout(() => {
      notification.style.transform = 'translateX(full)';
      setTimeout(() => {
        if (notification.parentNode) {
          notification.remove();
        }
      }, 300);
    }, 4000);
  };

  // Obține icon-ul pentru tipul de fișier
  const getFileIcon = (fileType) => {
    if (!fileType) return '📋';
    const type = fileType.toLowerCase();
    if (type.includes('pdf')) return '📄';
    if (type.includes('word') || type.includes('document')) return '📝';
    if (type.includes('excel') || type.includes('sheet')) return '📊';
    if (type.includes('image')) return '🖼️';
    if (type.includes('text')) return '📃';
    return '📋';
  };

  // Formatează dimensiunea fișierului
  const formatFileSize = (bytes) => {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
  };

  if (loading) {
    return (
      <section className="py-16 px-6">
        <div className="container mx-auto text-center">
          <div className="animate-spin w-16 h-16 border-4 border-[#b06b4c] border-t-transparent rounded-full mx-auto mb-4"></div>
          <p className="text-gray-600">Se încarcă documentele...</p>
        </div>
      </section>
    );
  }

  return (
    <section className="py-16 px-6">
      <div className="container mx-auto max-w-7xl">
        {/* Status indicator */}
        <div className="mb-8 p-4 bg-blue-100 border border-blue-300 rounded-lg text-center">
          <p className="text-blue-800">
            🔧 <strong>Mode:</strong> {isDevelopment ? 'Development' : 'Production'} | 
            📊 <strong>Documente:</strong> {documents.length} | 
            {error && <span className="text-red-600">⚠️ {error}</span>}
            {!error && <span className="text-green-600">✅ API conectat</span>}
          </p>
        </div>

        {/* Header */}
        <div className="text-center mb-12">
          <h2 className="text-3xl md:text-4xl font-bold text-[#b06b4c] mb-4">
            📚 Documentele Disponibile
          </h2>
          <p className="text-gray-600 max-w-2xl mx-auto">
            Descarcă gratuit ghidurile și resursele create special pentru tine
          </p>
        </div>

        {/* Filtre categorii */}
        <div className="flex flex-wrap justify-center gap-3 mb-12">
          {categories.map((category) => (
            <button
              key={category.value}
              onClick={() => setSelectedCategory(category.value)}
              className={`px-4 py-2 rounded-full text-sm font-medium transition-all duration-200 ${
                selectedCategory === category.value
                  ? 'bg-[#b06b4c] text-white shadow-lg'
                  : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'
              }`}
            >
              {category.icon} {category.label}
            </button>
          ))}
        </div>

        {/* Grid de documente */}
        {filteredDocuments.length === 0 ? (
          <div className="text-center py-16">
            <div className="text-6xl mb-4">📂</div>
            <h3 className="text-xl font-semibold text-gray-700 mb-2">
              Nu există documente în această categorie
            </h3>
            <p className="text-gray-500">
              Verifică alte categorii sau revino mai târziu pentru noi resurse
            </p>
            <button 
              onClick={loadDocuments}
              className="mt-4 bg-[#b06b4c] text-white px-6 py-2 rounded-lg hover:bg-amber-600 transition-colors duration-200"
            >
              🔄 Reîncarcă
            </button>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {filteredDocuments.map((document) => (
              <div
                key={document.id}
                className="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden group hover:-translate-y-2"
              >
                {/* Header card */}
                <div className="p-6 border-b border-gray-100">
                  <div className="flex items-start justify-between mb-4">
                    <div className="w-12 h-12 bg-gradient-to-br from-[#b06b4c] to-amber-600 rounded-xl flex items-center justify-center text-2xl">
                      {getFileIcon(document.file_type)}
                    </div>
                    {document.is_featured && (
                      <span className="bg-gradient-to-r from-amber-400 to-orange-400 text-white text-xs px-2 py-1 rounded-full font-semibold">
                        ⭐ Recomandat
                      </span>
                    )}
                  </div>
                  
                  <h3 className="text-lg font-bold text-gray-900 mb-2 line-clamp-2">
                    {document.title}
                  </h3>
                  
                  {document.description && (
                    <p className="text-sm text-gray-600 line-clamp-3">
                      {document.description}
                    </p>
                  )}
                </div>

                {/* Info și acțiuni */}
                <div className="p-6">
                  <div className="flex items-center justify-between text-sm text-gray-500 mb-4">
                    <span className="flex items-center">
                      📊 {formatFileSize(document.file_size)}
                    </span>
                    <span className="flex items-center">
                      📥 {document.downloads_count || 0} descărcări
                    </span>
                  </div>

                  {/* Preț */}
                  <div className="mb-4">
                    {document.is_free ? (
                      <span className="bg-green-100 text-green-800 text-sm font-semibold px-3 py-1 rounded-full">
                        💚 Gratuit
                      </span>
                    ) : (
                      <span className="bg-amber-100 text-amber-800 text-sm font-semibold px-3 py-1 rounded-full">
                        💰 {document.price} GBP
                      </span>
                    )}
                  </div>

                  {/* Buton download */}
                  <button
                    onClick={() => handleDownload(document.id, document.title)}
                    className="w-full bg-gradient-to-r from-[#b06b4c] to-amber-600 text-white font-semibold py-3 px-6 rounded-xl hover:from-amber-600 hover:to-[#b06b4c] transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl"
                  >
                    📥 Descarcă Acum
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Footer informativ */}
        <div className="text-center mt-16 p-8 bg-gradient-to-r from-amber-50 to-orange-50 rounded-2xl">
          <h3 className="text-xl font-bold text-[#b06b4c] mb-4">
            💡 Cum să folosești documentele?
          </h3>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm text-gray-600">
            <div>
              <span className="text-2xl block mb-2">📱</span>
              <strong>Salvează pe telefon</strong> pentru acces rapid oricând
            </div>
            <div>
              <span className="text-2xl block mb-2">📝</span>
              <strong>Printează</strong> formularele pentru completare manuală
            </div>
            <div>
              <span className="text-2xl block mb-2">📚</span>
              <strong>Studiază</strong> ghidurile pas cu pas pentru rezultate optime
            </div>
          </div>
        </div>
      </div>
    </section>
  );
};

export default DocumentsPublic;