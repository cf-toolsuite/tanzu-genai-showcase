import React from 'react';

function SampleInquiries({ isFirstRun, onQuestionClick, disabled }) {
  const firstRunQuestions = [
    { text: 'Action', query: 'Show me action movies playing this weekend' },
    { text: 'Comedy', query: 'Recommend a great comedy movie for a double-date' },
    { text: 'Documentary', query: 'What thought-provoking documentary should I not miss this month?' },
    { text: 'Family', query: 'I want to see a family movie with my kids', className: 'd-none d-md-inline-block' },
  ];

  const casualQuestions = [
    { text: 'Sci-Fi', query: 'Recommend some great sci-fi movies' },
    { text: 'Comedy', query: 'What are some great comedy movies from the last decade?' },
    { text: 'Horror', query: 'What are some classic horror films I should watch?' },
    { text: 'Thriller', query: 'Good psychological thrillers to watch', className: 'd-none d-md-inline-block' },
  ];

  const questions = isFirstRun ? firstRunQuestions : casualQuestions;

  return (
    <div className="sample-buttons">
      {questions.map((question, index) => (
        <button
          key={index}
          className={`btn btn-sample ${question.className || ''}`}
          onClick={() => {
            if (!disabled) {
              console.log('Sample question clicked:', question.query);
              onQuestionClick(question.query);
            } else {
              console.log('Sample question click prevented: UI is disabled during processing');
            }
          }}
          disabled={disabled}
          style={disabled ? { opacity: 0.6, cursor: 'not-allowed' } : {}}
        >
          {question.text}
        </button>
      ))}
    </div>
  );
}

export default SampleInquiries;
