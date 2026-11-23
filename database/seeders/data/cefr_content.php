<?php

/**
 * CEFR content taken verbatim from docs/source/ICP_English_Progress_Report.xlsx
 * (sheets "Descriptors" and "Settings").
 */
return [
    'levels' => [
        'Pre-A1' => [
            'sort' => 1,
            'short_name' => 'Pre-Beginner',
            'name' => 'Pre-Beginner',
            'cambridge' => 'Pre A1 Starters',
            'color_hex' => '#F4A261',
            'overall_comment' => 'has started building a foundation in English and can recognise familiar words and simple phrases. With regular practice of everyday vocabulary at home and at school, steady progress is expected.',
        ],
        'A1' => [
            'sort' => 2,
            'short_name' => 'Beginner',
            'name' => 'Beginner (Breakthrough)',
            'cambridge' => 'A1 Movers',
            'color_hex' => '#E9C46A',
            'overall_comment' => 'can communicate in English using simple words and sentences on familiar topics. More practice in making full sentences and asking questions will help to reach the next level.',
        ],
        'A2' => [
            'sort' => 3,
            'short_name' => 'Elementary',
            'name' => 'Elementary (Waystage)',
            'cambridge' => 'A2 Flyers / A2 Key',
            'color_hex' => '#1F7A70',
            'overall_comment' => 'can understand and use English in everyday situations, such as talking about school, family and past events. Reading more and speaking in longer sentences will support further growth.',
        ],
        'B1' => [
            'sort' => 4,
            'short_name' => 'Intermediate',
            'name' => 'Intermediate (Threshold)',
            'cambridge' => 'B1 Preliminary',
            'color_hex' => '#457B9D',
            'overall_comment' => 'uses English confidently on familiar topics, gives opinions with reasons and understands the main ideas of longer texts. Wider reading and discussion will build even greater accuracy and fluency.',
        ],
        'B2' => [
            'sort' => 5,
            'short_name' => 'Upper Intermediate',
            'name' => 'Upper Intermediate (Vantage)',
            'cambridge' => 'B2 First',
            'color_hex' => '#6A4C93',
            'overall_comment' => 'shows an impressive command of English, expressing ideas clearly and understanding complex texts. Continued challenge through advanced reading, writing and speaking is highly recommended.',
        ],
    ],

    'can_do' => [
        'Pre-A1' => [
            'listening' => 'Can recognise familiar words and very simple phrases about themselves, their family and the classroom when people speak slowly and clearly.',
            'reading' => 'Can recognise familiar words and understand very short, simple sentences with the help of pictures or context.',
            'writing' => 'Can copy and write familiar words and short phrases with mostly correct spelling.',
            'speaking' => 'Can answer very simple questions about themselves with single words or short phrases.',
        ],
        'A1' => [
            'listening' => 'Can understand simple questions, instructions, names and numbers in short, slow conversations on familiar topics.',
            'reading' => 'Can understand short, simple texts and notices and find basic information such as names, places and times.',
            'writing' => "Can write simple sentences about themselves and familiar things using basic linking words such as 'and' and 'but'.",
            'speaking' => 'Can ask and answer simple questions about familiar topics and describe people and things in short sentences.',
        ],
        'A2' => [
            'listening' => 'Can understand the main points of short, clear conversations and stories about everyday topics such as school, family and holidays.',
            'reading' => 'Can understand short texts such as emails, stories and notices, and find both specific information and main ideas.',
            'writing' => "Can write short connected texts such as messages and simple paragraphs about everyday experiences using 'because', 'so' and 'then'.",
            'speaking' => 'Can talk about daily routines, past events and preferences in simple connected sentences and take part in short conversations.',
        ],
        'B1' => [
            'listening' => 'Can follow the main ideas and key details of longer talks and conversations on familiar topics in clear standard English.',
            'reading' => 'Can understand the main ideas of longer factual and narrative texts and follow the order of events and reasons.',
            'writing' => 'Can write organised paragraphs about experiences and opinions, giving reasons and examples with reasonable accuracy.',
            'speaking' => 'Can describe experiences, give opinions with reasons and keep a conversation going on familiar topics.',
        ],
        'B2' => [
            'listening' => "Can understand extended talks and discussions, including speakers' opinions and reasons, on a wide range of topics.",
            'reading' => "Can read longer texts with understanding of the writer's viewpoint, implied meaning and new words from context.",
            'writing' => 'Can write clear, well-structured texts that express and support opinions, using a range of vocabulary and linking words.',
            'speaking' => 'Can express and defend opinions clearly, discuss a range of topics fluently and interact naturally with others.',
        ],
    ],

    'next_step' => [
        'Pre-A1' => [
            'listening' => 'Listen to simple English songs and picture stories every day and point to the words you hear.',
            'reading' => 'Read picture books together at home and practise recognising common sight words.',
            'writing' => 'Practise writing familiar words and labelling pictures with the correct spelling.',
            'speaking' => 'Practise greetings and answering simple questions about yourself every day.',
        ],
        'A1' => [
            'listening' => 'Practise following simple instructions and listen to short dialogues to catch names, numbers and times.',
            'reading' => 'Read short, simple stories regularly and answer who / what / where questions about them.',
            'writing' => 'Write 3–5 simple sentences about daily life and check capital letters and full stops.',
            'speaking' => 'Practise asking and answering questions with a partner using full sentences.',
        ],
        'A2' => [
            'listening' => 'Watch short English videos or listen to stories and retell the main events in your own words.',
            'reading' => 'Read one short book each week and practise finding the main idea of each paragraph.',
            'writing' => "Keep a short weekly journal using linking words such as 'because', 'so' and 'then'.",
            'speaking' => 'Tell short stories about your day or weekend, using past tense verbs.',
        ],
        'B1' => [
            'listening' => 'Listen to podcasts or news for kids and take notes on the main ideas and important details.',
            'reading' => 'Read a variety of texts such as articles and stories, and learn new words from context.',
            'writing' => 'Plan paragraphs with a topic sentence, supporting details and a conclusion.',
            'speaking' => 'Join class discussions and practise giving opinions with reasons (I think … because …).',
        ],
        'B2' => [
            'listening' => "Explore longer talks and documentaries and practise identifying each speaker's opinion and attitude.",
            'reading' => "Read longer books and articles and discuss the writer's purpose and point of view.",
            'writing' => 'Write opinion texts with clear arguments, varied vocabulary and careful self-editing.',
            'speaking' => 'Take part in presentations and debates to build fluency and natural interaction.',
        ],
    ],

    // Interim (revisi 23-11-2026): band texts reuse the CEFR texts above until the school sends its own.
    'band_source' => ['Beginner' => 'A1', 'Intermediate' => 'A2', 'Advanced' => 'B1'],

];
