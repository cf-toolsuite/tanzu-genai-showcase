import React from 'react';

function SampleInquiries({ isFirstRun, onQuestionClick }) {
  const firstRunQuestions = [
    { text: 'Action', query: 'Show me action movies playing this weekend' },
    { text: 'Comedy', query: 'Recommend a great comedy movie for a double-date' },
    { text: 'Documentary', query: 'What thought-provoking documentary should I not miss this month?' },
    { text: 'Family', query: 'I want to see a family movie with my kids', className: 'd-none d-md-inline-block' },
  ];

  const casualQuestions = [
    { text: 'Sci-Fi', query: 'Recommend some great sci-fi movies' },
    { text: 'Comedy', query: 'What are some great comedy movies from the last decade?' },
  ];

  const questions = isFirstRun ? firstRunQuestions : casualQuestions;

  return (
    <div className="sample-buttons">
      {questions.map((question, index) => (
        <button
          key={index}
          className={`btn btn-sample ${question.className || ''}`}
          onClick={() => onQuestionClick(question.query)}
        >
          {question.text}
        </button>
      ))}
    </div>
  );
}

export default SampleInquiries;
