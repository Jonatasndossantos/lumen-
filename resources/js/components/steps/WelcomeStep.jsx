import React from 'react';
import logo from '../../assets/logo.png';

const WelcomeStep = ({ nextStep }) => (
  <div className="flex flex-col items-center justify-center h-full rounded-xl p-8 shadow-lg">
    <div className="flex justify-center mb-4">
      <img src={logo} alt="Logo Lumen" className="h-20 w-auto" />
    </div>
    <h2 className="text-2xl font-bold mb-2 text-dark tracking-widest">LUMEN</h2>
    <h3 className="text-xl font-semibold mb-4 text-dark text-center leading-snug">
      Gerador de Documentos<br />
      Conforme a Nova Lei<br />
      de Licitações
    </h3>
    <p className="mb-4 text-center text-gray-600 text-base">
      Gere automaticamente todos os documentos necessários, elaborados conforme a Lei n° 14.133/2021,<br />
      para a conformidade do seu processo de contratação.
    </p>
    <p className="mb-8 text-center text-gray-700 font-medium">
      Blindagem documental garantida conforme a Nova Lei de Licitações.
    </p>
    <button
      onClick={nextStep}
      className="bg-yellow-400 hover:bg-yellow-500 text-dark font-bold py-3 px-6 rounded-lg text-base shadow-md transition-all duration-200 flex items-center gap-2"
    >
      <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
      </svg>
      Começar Construção dos Documentos
    </button>
  </div>
);

export default WelcomeStep;
